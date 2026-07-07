<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Recado\Sdk\Laravel\Facades\Recado;
use Recado\Sdk\RecadoClient;
use Recado\Sdk\Resources\ContactsResource;
use Psr\Http\Message\RequestInterface;

/**
 * Proves the Recado facade resolves the container-bound RecadoClient singleton
 * and proxies its resource accessors through to the real HTTP client.
 */
final class FacadeTest extends TestCase
{
    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['Recado' => Recado::class];
    }

    public function test_facade_root_is_the_container_singleton(): void
    {
        $this->assertInstanceOf(RecadoClient::class, Recado::getFacadeRoot());
        $this->assertSame($this->app->make(RecadoClient::class), Recado::getFacadeRoot());
    }

    public function test_resource_accessor_is_memoized(): void
    {
        $contacts = Recado::contacts();

        $this->assertInstanceOf(ContactsResource::class, $contacts);
        $this->assertSame($contacts, Recado::contacts());
    }

    public function test_send_proxies_through_the_real_client(): void
    {
        $history = [];
        $this->bindMockClient([
            new Response(202, ['Content-Type' => 'application/json'], (string) json_encode([
                'data' => ['id' => 'msg-1', 'status' => 'queued'],
            ])),
        ], $history);

        Recado::send()->email([
            'to' => 'jane@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
        ]);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://recado.example.com/api/v1/send', (string) $request->getUri());

        $payload = json_decode((string) $request->getBody(), true);
        $this->assertSame('jane@example.com', $payload['to']);
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame('<p>Hi</p>', $payload['body']);
    }

    /**
     * Re-bind the container's RecadoClient singleton with one backed by a mock
     * HTTP handler, so the facade-resolved client uses it. The facade caches its
     * resolved root, so callers must clear it after rebinding.
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

        Recado::clearResolvedInstance(RecadoClient::class);
    }
}
