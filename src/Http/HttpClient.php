<?php

declare(strict_types=1);

namespace Recado\Sdk\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Recado\Sdk\Exception\AuthenticationException;
use Recado\Sdk\Exception\RecadoException;
use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\RateLimitException;
use Recado\Sdk\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Thin wrapper around Guzzle that owns request building (auth + JSON headers),
 * response decoding and the mapping from HTTP status to SDK exceptions.
 */
final class HttpClient
{
    private readonly ClientInterface $client;

    /**
     * @param array<string, mixed> $options Transport/resilience options applied
     *                                       only when no client is injected:
     *                                       `retries`, `retry_base_delay`,
     *                                       `retry_max_delay`, `retry_on_status`,
     *                                       `timeout`, `connect_timeout`.
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        ?ClientInterface $client = null,
        array $options = [],
    ) {
        $this->client = $client ?? $this->buildClient($options);
    }

    /**
     * Build the default Guzzle client with the automatic-retry middleware and
     * optional timeouts. Used only when no client is injected (an injected
     * client is taken as-is so callers keep full control).
     *
     * @param array<string, mixed> $options
     */
    private function buildClient(array $options): ClientInterface
    {
        $stack = HandlerStack::create();
        $stack->push(RetryMiddleware::make($options));

        $config = ['handler' => $stack];

        if (isset($options['timeout'])) {
            $config['timeout'] = $options['timeout'];
        }

        if (isset($options['connect_timeout'])) {
            $config['connect_timeout'] = $options['connect_timeout'];
        }

        return new Client($config);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function get(string $path, array $options = []): array
    {
        return $this->request('GET', $path, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function post(string $path, array $options = []): array
    {
        return $this->request('POST', $path, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function patch(string $path, array $options = []): array
    {
        return $this->request('PATCH', $path, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function put(string $path, array $options = []): array
    {
        return $this->request('PUT', $path, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function delete(string $path, array $options = []): array
    {
        return $this->request('DELETE', $path, $options);
    }

    /**
     * Execute the request and decode the response, mapping failures to exceptions.
     *
     * Supported custom options:
     *  - `json`:            request body, JSON-encoded by Guzzle.
     *  - `query`:           query string parameters.
     *  - `idempotency_key`: value for the `Idempotency-Key` header.
     *  - `headers`:         additional headers, merged over the defaults.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ];

        if (array_key_exists('json', $options)) {
            $headers['Content-Type'] = 'application/json';
        }

        if (isset($options['idempotency_key'])) {
            $headers['Idempotency-Key'] = (string) $options['idempotency_key'];
            unset($options['idempotency_key']);
        }

        if (isset($options['headers']) && is_array($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        $options['headers'] = $headers;
        $options['http_errors'] = false;

        $response = $this->client->request($method, $this->url($path), $options);

        return $this->handle($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $decoded = $this->decode($raw);

        if ($status >= 200 && $status < 300) {
            return $decoded;
        }

        throw $this->mapException($status, $decoded, $response);
    }

    /**
     * Decode a JSON body to an associative array; empty/invalid bodies yield [].
     *
     * @return array<string, mixed>
     */
    private function decode(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function mapException(int $status, array $body, ResponseInterface $response): RecadoException
    {
        $message = isset($body['message']) && is_string($body['message'])
            ? $body['message']
            : 'Request failed with status '.$status;
        $code = isset($body['code']) && is_string($body['code']) ? $body['code'] : null;

        return match ($status) {
            401 => new AuthenticationException($message, $code, $status, $body),
            404 => new NotFoundException($message, $code, $status, $body),
            422 => new ValidationException(
                $message,
                $this->normalizeErrors($body['errors'] ?? []),
                $code,
                $status,
                $body,
            ),
            429 => new RateLimitException(
                $message,
                $this->parseRetryAfter($response),
                $code,
                $status,
                $body,
            ),
            default => new RecadoException($message, $code, $status, $body),
        };
    }

    /**
     * @param mixed $errors
     *
     * @return array<string, array<int, string>>
     */
    private function normalizeErrors(mixed $errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        $normalized = [];

        foreach ($errors as $field => $messages) {
            $normalized[(string) $field] = is_array($messages)
                ? array_values(array_map('strval', $messages))
                : [(string) $messages];
        }

        return $normalized;
    }

    private function parseRetryAfter(ResponseInterface $response): ?int
    {
        $header = $response->getHeaderLine('Retry-After');

        if ($header === '') {
            return null;
        }

        return is_numeric($header) ? (int) $header : null;
    }

    private function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }
}
