<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\NotificationBatchItem;
use Recado\Sdk\Dto\NotificationBatchResult;

final class NotificationsBatchTest extends TestCase
{
    public function test_batch_parses_per_item_channel_results_and_counts(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => [
                    'messages' => [
                        [
                            'index' => 0,
                            'to' => 'jane@example.com',
                            'results' => [
                                ['channel' => 'in_app', 'id' => 'uuid-0', 'status' => 'queued'],
                            ],
                        ],
                        [
                            'index' => 1,
                            'to' => 'john@example.com',
                            'results' => [
                                ['channel' => 'in_app', 'id' => 'uuid-1', 'status' => 'queued'],
                                ['channel' => 'push', 'id' => null, 'status' => 'failed_precondition', 'error_code' => 'push_provider_not_configured'],
                            ],
                        ],
                    ],
                    'queued' => 2,
                    'failed' => 1,
                ],
            ]),
        ], $history);

        $result = $client->notifications()->batch([
            ['to' => 'jane@example.com', 'title' => 'Hi', 'body' => 'There'],
            ['to' => 'john@example.com', 'title' => 'Hi', 'body' => 'There', 'channels' => ['in_app', 'push']],
        ]);

        $this->assertInstanceOf(NotificationBatchResult::class, $result);
        $this->assertSame(2, $result->queued);
        $this->assertSame(1, $result->failed);
        $this->assertCount(2, $result->messages);

        $this->assertInstanceOf(NotificationBatchItem::class, $result->messages[0]);
        $this->assertSame(0, $result->messages[0]->index);
        $this->assertSame('jane@example.com', $result->messages[0]->to);
        $this->assertTrue($result->messages[0]->anyQueued());

        $push = $result->messages[1]->channel('push');
        $this->assertNotNull($push);
        $this->assertFalse($push->queued());
        $this->assertSame('push_provider_not_configured', $push->errorCode);
        $this->assertNull($push->id);
        // One channel failing never hides the item's other outcome.
        $this->assertTrue($result->messages[1]->anyQueued());

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/notifications/batch', $request->getUri()->getPath());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertCount(2, $body['messages']);
        $this->assertSame('jane@example.com', $body['messages'][0]['to']);
    }

    public function test_batch_sends_the_idempotency_key_header(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['messages' => [], 'queued' => 0, 'failed' => 0]]),
        ], $history);

        $client->notifications()->batch(
            [['to' => 'jane@example.com', 'title' => 'Hi', 'body' => 'There']],
            idempotencyKey: 'notify-batch-1',
        );

        $this->assertSame('notify-batch-1', $history[0]['request']->getHeaderLine('Idempotency-Key'));
    }

    public function test_batch_reindexes_a_sparse_messages_array(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['messages' => [], 'queued' => 0, 'failed' => 0]]),
        ], $history);

        $client->notifications()->batch([
            3 => ['to' => 'jane@example.com', 'title' => 'Hi', 'body' => 'There'],
        ]);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayHasKey(0, $body['messages']);
    }
}
