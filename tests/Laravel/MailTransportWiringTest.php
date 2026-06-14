<?php

declare(strict_types=1);

namespace Mailer\Sdk\Tests\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Mail;
use Mailer\Sdk\MailerClient;
use Psr\Http\Message\RequestInterface;

/**
 * End-to-end proof that MAIL_MAILER=mailer routes Laravel's Mail facade through
 * the transport, the service provider's Mail::extend('mailer') registration and
 * the container-resolved MailerClient singleton.
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

        Mail::raw('Hello world', function ($message): void {
            $message->to('jane@example.com')->subject('Hello');
        });

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.mailer.test/api/v1/send', (string) $request->getUri());

        $payload = json_decode((string) $request->getBody(), true);
        $this->assertSame('jane@example.com', $payload['to']);
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame('Hello world', $payload['body']);
        $this->assertArrayNotHasKey('from', $payload);
    }

    /**
     * Re-bind the container's MailerClient singleton with one backed by a mock
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
            MailerClient::class,
            new MailerClient('https://api.mailer.test/api/v1', 'test-token', $guzzle),
        );
    }
}
