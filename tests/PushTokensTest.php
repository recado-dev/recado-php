<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\ValidationException;

final class PushTokensTest extends TestCase
{
    public function test_register_sends_body_and_parses_result(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['registered' => true, 'devices' => 3]]),
        ], $history);

        $result = $client->push()->register('jane@example.com', 'device-token', 'ios');

        $this->assertTrue($result->registered);
        $this->assertSame(3, $result->devices);
        $this->assertNull($result->removed);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/push/tokens', $request->getUri()->getPath());
        $this->assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('jane@example.com', $body['email']);
        $this->assertSame('device-token', $body['token']);
        $this->assertSame('ios', $body['platform']);
        // No app → the field is not sent at all (default app).
        $this->assertArrayNotHasKey('app', $body);
    }

    public function test_register_sends_the_push_app_key_when_given(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['registered' => true, 'devices' => 1]]),
        ], $history);

        $client->push()->register('jane@example.com', 'device-token', 'ios', 'admin');

        $body = json_decode((string) $history[0]['request']->getBody(), true);

        $this->assertSame(
            ['email' => 'jane@example.com', 'token' => 'device-token', 'platform' => 'ios', 'app' => 'admin'],
            $body,
        );
    }

    public function test_register_with_an_unknown_app_raises_a_coded_validation_error(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The push app "ghost" does not exist or is disabled.',
                'code' => 'push_app_not_found',
            ]),
        ], $history);

        try {
            $client->push()->register('jane@example.com', 'device-token', 'ios', 'ghost');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('push_app_not_found', $e->getErrorCode());
        }
    }

    public function test_remove_can_be_narrowed_to_one_push_app(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['removed' => true]]),
        ], $history);

        $client->push()->remove('jane@example.com', 'device-token', 'admin');

        $body = json_decode((string) $history[0]['request']->getBody(), true);

        $this->assertSame(['email' => 'jane@example.com', 'token' => 'device-token', 'app' => 'admin'], $body);
    }

    public function test_remove_sends_delete_with_json_body_and_removed_true(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['removed' => true]]),
        ], $history);

        $result = $client->push()->remove('jane@example.com', 'device-token');

        $this->assertTrue($result->removed);
        $this->assertNull($result->registered);

        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/api/v1/push/tokens', $request->getUri()->getPath());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('jane@example.com', $body['email']);
        $this->assertSame('device-token', $body['token']);
        $this->assertArrayNotHasKey('app', $body);
    }

    public function test_remove_reports_false_when_token_absent(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['removed' => false]]),
        ], $history);

        $result = $client->push()->remove('jane@example.com', 'unknown-token');

        $this->assertFalse($result->removed);
    }

    public function test_remove_unknown_contact_raises_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, [
                'message' => 'Contact not found.',
                'code' => 'contact_not_found',
            ]),
        ], $history);

        try {
            $client->push()->remove('missing@example.com', 'device-token');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame('contact_not_found', $e->getErrorCode());
            $this->assertSame(404, $e->getStatus());
        }
    }
}
