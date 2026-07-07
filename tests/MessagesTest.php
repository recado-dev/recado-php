<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Message;
use Recado\Sdk\Dto\MessageEvent;

final class MessagesTest extends TestCase
{
    public function test_get_parses_message_with_ordered_events(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [
                    'uuid' => 'm-1',
                    'to_email' => 'jane@example.com',
                    'from_email' => 'hello@project.test',
                    'subject' => 'Welcome',
                    'status' => 'delivered',
                    'source' => 'api',
                    'campaign_id' => null,
                    'automation_id' => null,
                    'error' => null,
                    'sent_at' => '2026-01-01T00:00:01Z',
                    'created_at' => '2026-01-01T00:00:00Z',
                    'events' => [
                        ['type' => 'sent', 'payload' => [], 'occurred_at' => '2026-01-01T00:00:01Z'],
                        ['type' => 'delivered', 'payload' => ['smtp' => '250 OK'], 'occurred_at' => '2026-01-01T00:00:05Z'],
                        ['type' => 'opened', 'payload' => ['user_agent' => 'Mail'], 'occurred_at' => '2026-01-01T00:10:00Z'],
                    ],
                ],
            ]),
        ], $history);

        $message = $client->messages()->get('m-1');

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame('jane@example.com', $message->toEmail);
        $this->assertSame('hello@project.test', $message->fromEmail);
        $this->assertSame('delivered', $message->status);
        $this->assertSame('api', $message->source);
        $this->assertNull($message->campaignId);

        $this->assertCount(3, $message->events);
        $this->assertContainsOnlyInstancesOf(MessageEvent::class, $message->events);
        $this->assertSame('sent', $message->events[0]->type);
        $this->assertSame('delivered', $message->events[1]->type);
        $this->assertSame('250 OK', $message->events[1]->payload['smtp']);
        $this->assertSame('opened', $message->events[2]->type);

        $request = $history[0]['request'];
        $this->assertSame('/api/v1/messages/m-1', $request->getUri()->getPath());
    }

    public function test_list_parses_paginated_messages(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [
                    [
                        'uuid' => 'm-1',
                        'to_email' => 'jane@example.com',
                        'subject' => 'Welcome',
                        'status' => 'sent',
                        'source' => 'api',
                    ],
                ],
                'meta' => ['total' => 1, 'current_page' => 1],
                'links' => [],
            ]),
        ], $history);

        $page = $client->messages()->list(['status' => 'sent']);

        $this->assertCount(1, $page->data);
        $this->assertSame('m-1', $page->data[0]->uuid);
        $this->assertSame(1, $page->meta['total']);
        $this->assertSame([], $page->data[0]->events);
    }
}
