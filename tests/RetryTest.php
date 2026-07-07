<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use Recado\Sdk\Exception\RecadoException;
use Recado\Sdk\Http\RetryMiddleware;
use Recado\Sdk\RecadoClient;

final class RetryTest extends TestCase
{
    /**
     * Build a RecadoClient whose transport replays the queued responses through
     * a handler stack carrying the retry middleware, capturing every attempt.
     *
     * @param array<int, mixed>   $responses
     * @param array<string, mixed> $config
     * @param array<int, mixed>   $history
     */
    private function clientWithRetry(array $responses, array $config, array &$history): RecadoClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(RetryMiddleware::make($config));
        $stack->push(Middleware::history($history));

        $guzzle = new Client(['handler' => $stack]);

        return new RecadoClient($this->baseUrl, $this->token, $guzzle);
    }

    public function test_5xx_then_200_succeeds_after_retry(): void
    {
        $history = [];
        $client = $this->clientWithRetry([
            $this->jsonResponse(503, ['message' => 'Service Unavailable']),
            $this->jsonResponse(200, ['data' => [['id' => 7, 'name' => 'News']], 'meta' => ['current_page' => 1, 'last_page' => 1], 'links' => []]),
        ], ['retries' => 2, 'retry_base_delay' => 1, 'retry_max_delay' => 1], $history);

        $page = $client->lists()->list();

        $this->assertCount(1, $page->data);
        $this->assertCount(2, $history, 'Expected one retry after the 503.');
    }

    public function test_connect_exception_then_200_is_retried(): void
    {
        $history = [];
        $client = $this->clientWithRetry([
            new ConnectException('Connection refused', new Request('GET', 'lists')),
            $this->jsonResponse(200, ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1], 'links' => []]),
        ], ['retries' => 2, 'retry_base_delay' => 1, 'retry_max_delay' => 1], $history);

        $page = $client->lists()->list();

        $this->assertCount(0, $page->data);
        $this->assertCount(2, $history, 'Expected one retry after the ConnectException.');
    }

    public function test_max_retries_exhausted_throws_mapped_exception(): void
    {
        $history = [];
        $client = $this->clientWithRetry([
            $this->jsonResponse(500, ['message' => 'Boom']),
            $this->jsonResponse(500, ['message' => 'Boom']),
            $this->jsonResponse(500, ['message' => 'Boom']),
        ], ['retries' => 2, 'retry_base_delay' => 1, 'retry_max_delay' => 1], $history);

        try {
            $client->lists()->list();
            $this->fail('Expected RecadoException after exhausting retries.');
        } catch (RecadoException $e) {
            $this->assertSame(500, $e->getStatus());
        }

        $this->assertCount(3, $history, 'Expected retries + 1 attempts (initial + 2 retries).');
    }

    public function test_post_without_idempotency_key_is_not_retried(): void
    {
        $history = [];
        $client = $this->clientWithRetry([
            $this->jsonResponse(503, ['message' => 'Service Unavailable']),
            $this->jsonResponse(200, ['data' => ['id' => '1', 'status' => 'queued']]),
        ], ['retries' => 2, 'retry_base_delay' => 1, 'retry_max_delay' => 1], $history);

        try {
            $client->send()->email(['to' => 'jane@example.com', 'template' => 'welcome']);
            $this->fail('Expected the 503 to surface without a retry.');
        } catch (RecadoException $e) {
            $this->assertSame(503, $e->getStatus());
        }

        $this->assertCount(1, $history, 'A POST without Idempotency-Key must never be retried.');
    }

    public function test_post_with_idempotency_key_is_retried(): void
    {
        $history = [];
        $client = $this->clientWithRetry([
            $this->jsonResponse(503, ['message' => 'Service Unavailable']),
            $this->jsonResponse(200, ['data' => ['id' => '1', 'status' => 'queued']]),
        ], ['retries' => 2, 'retry_base_delay' => 1, 'retry_max_delay' => 1], $history);

        $sent = $client->send()->email(
            ['to' => 'jane@example.com', 'template' => 'welcome'],
            idempotencyKey: 'order-1234',
        );

        $this->assertSame('1', $sent->id);
        $this->assertCount(2, $history, 'A POST with an Idempotency-Key is safe to retry.');

        // The retried request carries the same Idempotency-Key header.
        $this->assertSame('order-1234', $history[1]['request']->getHeaderLine('Idempotency-Key'));
    }

    public function test_429_then_200_is_retried(): void
    {
        $history = [];
        $client = $this->clientWithRetry([
            $this->jsonResponse(429, ['message' => 'Too Many Attempts.'], ['Retry-After' => '0']),
            $this->jsonResponse(200, ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1], 'links' => []]),
        ], ['retries' => 2, 'retry_base_delay' => 1, 'retry_max_delay' => 1], $history);

        $page = $client->lists()->list();

        $this->assertCount(0, $page->data);
        $this->assertCount(2, $history, 'A 429 must be retried.');
    }

    public function test_429_retry_after_is_capped_at_max_delay(): void
    {
        // A hostile/misconfigured Retry-After of one hour must NOT block the
        // worker: it is capped at retry_max_delay. If uncapped, honoring the
        // header would sleep ~3600s here and hang the suite — so the elapsed
        // time itself is the assertion.
        $history = [];
        $client = $this->clientWithRetry([
            $this->jsonResponse(429, ['message' => 'Too Many Attempts.'], ['Retry-After' => '3600']),
            $this->jsonResponse(200, ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1], 'links' => []]),
        ], ['retries' => 2, 'retry_base_delay' => 1, 'retry_max_delay' => 5], $history);

        $start = microtime(true);
        $page = $client->lists()->list();
        $elapsed = microtime(true) - $start;

        $this->assertCount(0, $page->data);
        $this->assertCount(2, $history, 'The 429 must be retried once.');
        $this->assertLessThan(1.0, $elapsed, 'Retry-After must be capped at retry_max_delay, not honored verbatim.');
    }

    public function test_retry_after_parser_handles_seconds(): void
    {
        $this->assertSame(37000, RetryMiddleware::parseRetryAfter('37'));
        $this->assertSame(0, RetryMiddleware::parseRetryAfter('0'));
        $this->assertNull(RetryMiddleware::parseRetryAfter(''));
        $this->assertNull(RetryMiddleware::parseRetryAfter('   '));
    }

    public function test_retry_after_parser_handles_http_date(): void
    {
        // A date 10 seconds in the future should yield roughly 10s in ms.
        $future = gmdate('D, d M Y H:i:s \G\M\T', time() + 10);
        $ms = RetryMiddleware::parseRetryAfter($future);

        $this->assertNotNull($ms);
        $this->assertGreaterThanOrEqual(8000, $ms);
        $this->assertLessThanOrEqual(11000, $ms);

        // A date in the past floors at 0.
        $past = gmdate('D, d M Y H:i:s \G\M\T', time() - 60);
        $this->assertSame(0, RetryMiddleware::parseRetryAfter($past));

        // Unparseable values yield null.
        $this->assertNull(RetryMiddleware::parseRetryAfter('not-a-date'));
    }
}
