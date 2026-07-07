<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\BatchItem;

final class BatchTest extends TestCase
{
    public function test_batch_parses_per_item_results_and_counts(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => [
                    'messages' => [
                        ['index' => 0, 'status' => 'queued', 'id' => 'uuid-0'],
                        ['index' => 1, 'status' => 'suppressed', 'code' => 'recipient_suppressed'],
                    ],
                    'queued' => 1,
                    'failed' => 0,
                ],
            ]),
        ], $history);

        $result = $client->send()->batch([
            ['to' => 'a@example.com', 'template' => 'welcome'],
            ['to' => 'b@example.com', 'template' => 'welcome'],
        ]);

        $this->assertSame(1, $result->queued);
        $this->assertSame(0, $result->failed);
        $this->assertCount(2, $result->messages);

        $this->assertInstanceOf(BatchItem::class, $result->messages[0]);
        $this->assertSame(0, $result->messages[0]->index);
        $this->assertSame('queued', $result->messages[0]->status);
        $this->assertSame('uuid-0', $result->messages[0]->id);

        $this->assertSame('suppressed', $result->messages[1]->status);
        $this->assertSame('recipient_suppressed', $result->messages[1]->code);
        $this->assertNull($result->messages[1]->id);

        $request = $history[0]['request'];
        $this->assertSame('/api/v1/send/batch', $request->getUri()->getPath());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertCount(2, $body['messages']);
        $this->assertSame('a@example.com', $body['messages'][0]['to']);
    }

    public function test_batch_sends_idempotency_key_header(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['messages' => [], 'queued' => 0, 'failed' => 0]]),
        ], $history);

        $client->send()->batch([
            ['to' => 'a@example.com', 'template' => 'welcome'],
        ], idempotencyKey: 'batch-key-1');

        $request = $history[0]['request'];
        $this->assertSame('batch-key-1', $request->getHeaderLine('Idempotency-Key'));
    }
}
