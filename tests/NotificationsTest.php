<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\NotificationChannelResult;
use Recado\Sdk\Dto\NotificationResult;
use Recado\Sdk\Exception\ValidationException;

final class NotificationsTest extends TestCase
{
    public function test_send_injects_in_app_channel_by_default(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['messages' => [
                    ['channel' => 'in_app', 'id' => 'uuid-0', 'status' => 'queued'],
                ]],
            ]),
        ], $history);

        $result = $client->notifications()->send([
            'to' => 'jane@example.com',
            'title' => 'Hi',
            'body' => 'There',
        ]);

        $this->assertInstanceOf(NotificationResult::class, $result);
        $this->assertTrue($result->anyQueued());

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/notifications', $request->getUri()->getPath());
        $this->assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(['in_app'], $body['channels']);
    }

    public function test_send_passes_explicit_channels_through(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['messages' => [
                    ['channel' => 'in_app', 'id' => 'uuid-0', 'status' => 'queued'],
                    ['channel' => 'push', 'id' => 'uuid-1', 'status' => 'queued'],
                ]],
            ]),
        ], $history);

        $client->notifications()->send([
            'to' => 'jane@example.com',
            'title' => 'Hi',
            'body' => 'There',
            'channels' => ['in_app', 'push'],
        ]);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame(['in_app', 'push'], $body['channels']);
    }

    public function test_send_hydrates_the_channel_envelope(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['messages' => [
                    ['channel' => 'in_app', 'id' => 'uuid-0', 'status' => 'queued'],
                ]],
            ]),
        ], $history);

        $result = $client->notifications()->send([
            'to' => 'jane@example.com',
            'title' => 'Hi',
            'body' => 'There',
        ]);

        $this->assertCount(1, $result->messages);
        $this->assertInstanceOf(NotificationChannelResult::class, $result->messages[0]);
        $channel = $result->channel('in_app');
        $this->assertNotNull($channel);
        $this->assertSame('uuid-0', $channel->id);
        $this->assertTrue($channel->queued());
        $this->assertNull($channel->errorCode);
    }

    public function test_send_mixed_outcome_reports_channel_failure_as_data(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['messages' => [
                    ['channel' => 'in_app', 'id' => 'uuid-0', 'status' => 'queued'],
                    ['channel' => 'push', 'id' => null, 'status' => 'failed_precondition', 'error_code' => 'push_provider_not_configured'],
                ]],
            ]),
        ], $history);

        $result = $client->notifications()->send([
            'to' => 'jane@example.com',
            'title' => 'Hi',
            'body' => 'There',
            'channels' => ['in_app', 'push'],
        ]);

        $this->assertTrue($result->anyQueued());
        $push = $result->channel('push');
        $this->assertNotNull($push);
        $this->assertFalse($push->queued());
        $this->assertSame('failed_precondition', $push->status);
        $this->assertSame('push_provider_not_configured', $push->errorCode);
        $this->assertNull($push->id);
    }

    public function test_send_all_failed_422_envelope_returns_result_without_throwing(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'data' => ['messages' => [
                    ['channel' => 'push', 'id' => null, 'status' => 'failed_precondition', 'error_code' => 'push_provider_not_configured'],
                ]],
            ]),
        ], $history);

        $result = $client->notifications()->send([
            'to' => 'jane@example.com',
            'title' => 'Hi',
            'body' => 'There',
            'channels' => ['push'],
        ]);

        $this->assertInstanceOf(NotificationResult::class, $result);
        $this->assertFalse($result->anyQueued());
        $this->assertSame('push_provider_not_configured', $result->channel('push')?->errorCode);
    }

    public function test_send_real_validation_422_still_throws(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The given data was invalid.',
                'errors' => ['title' => ['The title field is required.']],
            ]),
        ], $history);

        $this->expectException(ValidationException::class);

        $client->notifications()->send([
            'to' => 'jane@example.com',
            'body' => 'There',
        ]);
    }
}
