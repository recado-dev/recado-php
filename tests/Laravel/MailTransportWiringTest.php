<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Mail;
use Recado\Sdk\RecadoClient;
use Psr\Http\Message\RequestInterface;

/**
 * End-to-end proof that MAIL_MAILER=recado routes Laravel's Mail facade through
 * the transport, the service provider's Mail::extend('recado') registration and
 * the container-resolved RecadoClient singleton.
 */
final class MailTransportWiringTest extends TestCase
{
    public function test_mail_raw_is_sent_through_the_platform_send_endpoint(): void
    {
        $history = [];
        $this->bindMockClient([
            new Response(202, ['Content-Type' => 'application/json'], (string) json_encode([
                'data' => ['id' => 'msg-1', 'status' => 'queued'],
            ])),
        ], $history);

        config(['mail.from' => ['address' => 'app@example.com', 'name' => 'App']]);

        Mail::raw('Hello world', function ($message): void {
            $message->to('jane@example.com')->subject('Hello');
        });

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://recado.example.com/api/v1/send', (string) $request->getUri());

        $payload = json_decode((string) $request->getBody(), true);
        $this->assertSame('jane@example.com', $payload['to']);
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame('Hello world', $payload['body']);
        // Laravel stamps the app's global mail.from on every message, so it is
        // forwarded as the /send sender override (its domain must be verified
        // on the project). Set recado-sdk.mail.forward_from to false to always
        // fall back to the project's configured sender instead.
        $this->assertSame('app@example.com', $payload['from']);
        $this->assertSame('App', $payload['from_name']);
    }

    public function test_forward_from_disabled_leaves_the_sender_to_the_project(): void
    {
        $history = [];
        $this->bindMockClient([
            new Response(202, ['Content-Type' => 'application/json'], (string) json_encode([
                'data' => ['id' => 'msg-1', 'status' => 'queued'],
            ])),
        ], $history);

        config([
            'mail.from' => ['address' => 'app@example.com', 'name' => 'App'],
            'recado-sdk.mail.forward_from' => false,
        ]);

        Mail::raw('Hello world', function ($message): void {
            $message->to('jane@example.com')->subject('Hello');
        });

        $payload = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('from', $payload);
        $this->assertArrayNotHasKey('from_name', $payload);
    }

    /**
     * Re-bind the container's RecadoClient singleton with one backed by a mock
     * HTTP handler, so the real transport (built by Mail::extend) uses it.
     *
     * @param array<int, Response> $responses
     * @param array<int, array{request: RequestInterface}> $history
     */
    private function bindMockClient(array $responses, array &$history): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        $guzzle = new Client(['handler' => $stack]);

        $this->app->instance(
            RecadoClient::class,
            new RecadoClient('https://recado.example.com/api/v1', 'test-token', $guzzle),
        );
    }
}
