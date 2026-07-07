<?php

declare(strict_types=1);

namespace Recado\Sdk\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds a Guzzle retry middleware that transparently retries transient
 * failures (network errors, 5xx, 429) with exponential backoff, while staying
 * safe against duplicating non-idempotent requests.
 *
 * A request is only retried when it is safe to repeat: GET/HEAD/OPTIONS/PUT/
 * DELETE methods, or any request that carries an `Idempotency-Key` header
 * (which the API uses to deduplicate). A POST/PATCH without that header is
 * never retried, so a send can never be duplicated by the transport.
 */
final class RetryMiddleware
{
    /**
     * HTTP methods that are inherently safe to repeat.
     *
     * @var array<int, string>
     */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'];

    /**
     * Build the retry middleware.
     *
     * Supported config keys (with defaults):
     *  - `retries`          (int, 2):    maximum retry attempts.
     *  - `retry_base_delay` (int ms, 200): exponential backoff base.
     *  - `retry_max_delay`  (int ms, 5000): backoff cap (also caps a 429 Retry-After).
     *  - `retry_on_status`  (int[], 500..599): statuses to retry (429 is always retried).
     *
     * @param array<string, mixed> $config
     */
    public static function make(array $config = []): callable
    {
        $retries = isset($config['retries']) ? (int) $config['retries'] : 2;
        $baseDelay = isset($config['retry_base_delay']) ? (int) $config['retry_base_delay'] : 200;
        $maxDelay = isset($config['retry_max_delay']) ? (int) $config['retry_max_delay'] : 5000;
        $retryOnStatus = isset($config['retry_on_status']) && is_array($config['retry_on_status'])
            ? array_map('intval', $config['retry_on_status'])
            : range(500, 599);

        $decider = static function (
            int $attempt,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?\Throwable $exception = null,
        ) use ($retries, $retryOnStatus): bool {
            if ($attempt >= $retries) {
                return false;
            }

            if (! self::isRetryable($request)) {
                return false;
            }

            if ($exception instanceof ConnectException) {
                return true;
            }

            if ($response !== null) {
                $status = $response->getStatusCode();

                return $status === 429 || in_array($status, $retryOnStatus, true);
            }

            return false;
        };

        $delay = static function (int $attempt, ?ResponseInterface $response = null) use ($baseDelay, $maxDelay): int {
            if ($response !== null && $response->getStatusCode() === 429) {
                $retryAfter = self::parseRetryAfter($response->getHeaderLine('Retry-After'));

                if ($retryAfter !== null) {
                    // Honor the server hint, but never sleep longer than the
                    // configured cap: a hostile or misconfigured Retry-After
                    // must not block a synchronous worker indefinitely.
                    return min($retryAfter, $maxDelay);
                }
            }

            return self::backoff($attempt, $baseDelay, $maxDelay);
        };

        return Middleware::retry($decider, $delay);
    }

    /**
     * A request is retryable when its method is inherently safe to repeat, or
     * when it carries an Idempotency-Key header (the API deduplicates on it).
     */
    public static function isRetryable(RequestInterface $request): bool
    {
        if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return true;
        }

        return $request->hasHeader('Idempotency-Key');
    }

    /**
     * Exponential backoff in milliseconds, capped at the configured maximum.
     * No jitter is added so delays stay deterministic.
     */
    public static function backoff(int $attempt, int $baseDelay, int $maxDelay): int
    {
        return (int) min($maxDelay, $baseDelay * (2 ** $attempt));
    }

    /**
     * Parse a `Retry-After` header value into milliseconds. Accepts either a
     * number of seconds or an HTTP-date; returns null when the value is empty
     * or unparseable. The result is floored at 0.
     */
    public static function parseRetryAfter(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) max(0, (float) $value * 1000);
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        $deltaMs = ($timestamp - time()) * 1000;

        return (int) max(0, $deltaMs);
    }
}
