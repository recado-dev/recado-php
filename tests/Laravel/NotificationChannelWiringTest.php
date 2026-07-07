<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Notification;
use Recado\Sdk\Laravel\Mail\RecadoMessage;
use Recado\Sdk\RecadoClient;
use Psr\Http\Message\RequestInterface;

/**
 * End-to-end proof that a Notification with via() => ['recado'] routes through
 * the service provider's ChannelManager::extend('recado') registration, the
 * RecadoChannel and the container-resolved RecadoClient singleton.
 */
final class NotificationChannelWiringTest extends TestCase
{
    public function test_notification_is_delivered_through_the_platform_send_endpoint(): void
    {
        $history = [];
        $this->bindMockClient([
            new Response(202, ['Content-Type' => 'application/json'], (string) json_encode([
                'data' => ['id' => 'msg-1', 'status' => 'queued'],
            ])),
        ], $history);

        $notifiable = new class
        {
            use \Illuminate\Notifications\Notifiable;

            public string $email = 'jane@example.com';
        };

        $notification = new class extends \Illuminate\Notifications\Notification
        {
            /**
             * @return array<int, string>
             */
            public function via($notifiable): array
            {
                return ['recado'];
            }

            public function toRecado($notifiable): RecadoMessage
            {
                return (new RecadoMessage)->subject('Hi')->html('<p>Hello</p>');
            }
        };

        Notification::send([$notifiable], $notification);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://recado.example.com/api/v1/send', (string) $request->getUri());
    }

    /**
     * Re-bind the container's RecadoClient singleton with one backed by a mock
     * HTTP handler, so the real channel (built by ChannelManager::extend) uses it.
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
