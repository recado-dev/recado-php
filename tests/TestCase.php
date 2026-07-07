<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Recado\Sdk\RecadoClient;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The base URL used by the test client.
     */
    protected string $baseUrl = 'https://recado.example.com/api/v1';

    /**
     * The token used by the test client.
     */
    protected string $token = 'test-token';

    /**
     * Build a RecadoClient whose transport replays the given queued responses,
     * and capture every outgoing request into the returned `$history` array.
     *
     * @param array<int, Response> $responses
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, ...}> $history
     */
    protected function clientWithResponses(array $responses, array &$history): RecadoClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $guzzle = new Client(['handler' => $stack]);

        return new RecadoClient($this->baseUrl, $this->token, $guzzle);
    }

    /**
     * Convenience helper to build a JSON Guzzle response.
     *
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    protected function jsonResponse(int $status, array $body, array $headers = []): Response
    {
        return new Response(
            $status,
            array_merge(['Content-Type' => 'application/json'], $headers),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }
}
