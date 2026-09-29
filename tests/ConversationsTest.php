<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Conversation;
use Recado\Sdk\Dto\InboundReply;
use Recado\Sdk\Exception\NotFoundException;

/**
 * The Inbox surface: `replies()` (GET /replies) and `conversations()`
 * (GET /conversations[/{uuid}], PATCH /conversations/{uuid}).
 */
final class ConversationsTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function conversation(array $overrides = []): array
    {
        return array_merge([
            'uuid' => 'c-1',
            'subject' => 'Your order',
            'participant_email' => 'ana@example.org',
            'contact' => ['uuid' => 'ct-1', 'email' => 'ana@example.org'],
            'root_message' => [
                'uuid' => 'm-1',
                'subject' => 'Your order',
                'to_email' => 'ana@example.org',
                'source' => 'api',
                'template' => 'order-shipped',
                'campaign_id' => null,
                'automation_id' => null,
                'sent_at' => '2026-09-28T09:00:00+00:00',
            ],
            'unread' => true,
            'read_at' => null,
            'read_by' => null,
            'archived' => false,
            'archived_at' => null,
            'archived_by' => null,
            'last_inbound_at' => '2026-09-28T10:15:00+00:00',
            'last_outbound_at' => null,
            'last_activity_at' => '2026-09-28T10:15:00+00:00',
            'inbound_count' => 1,
            'outbound_count' => 0,
            'preview' => 'Where is my parcel?',
            'created_at' => '2026-09-28T10:15:00+00:00',
        ], $overrides);
    }

    public function test_replies_lists_the_whole_project_with_boolean_filters(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [[
                    'uuid' => 'r-1',
                    'message_uuid' => 'm-1',
                    'conversation_uuid' => 'c-1',
                    'contact' => null,
                    'from_email' => 'bob@example.org',
                    'stripped_text' => 'Hi',
                    'text' => 'Hi',
                    'received_at' => '2026-09-28T10:15:00+00:00',
                    'auth' => ['spf' => 'PASS', 'dkim' => 'PASS', 'dmarc' => 'PASS'],
                    'authenticated' => true,
                    'attachments' => [],
                ]],
                'meta' => ['current_page' => 1, 'total' => 1],
                'links' => ['next' => null],
            ]),
        ], $history);

        $page = $client->replies()->list(['human' => true, 'authenticated' => false, 'since' => '2026-09-01']);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/replies', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['human' => 'true', 'authenticated' => 'false', 'since' => '2026-09-01'], $query);

        $reply = $page->data[0];
        $this->assertInstanceOf(InboundReply::class, $reply);
        $this->assertSame('c-1', $reply->conversationUuid);
    }

    public function test_conversations_list_is_typed(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->conversation()],
                'meta' => ['current_page' => 1, 'total' => 1],
                'links' => ['next' => null],
            ]),
        ], $history);

        $page = $client->conversations()->list(['archived' => false, 'unread' => true]);

        $this->assertSame('/api/v1/conversations', $history[0]['request']->getUri()->getPath());
        $this->assertSame('archived=false&unread=true', $history[0]['request']->getUri()->getQuery());

        $conversation = $page->data[0];
        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertSame('c-1', $conversation->uuid);
        $this->assertSame('ct-1', $conversation->contactUuid);
        $this->assertSame('m-1', $conversation->rootMessage?->uuid);
        $this->assertSame('order-shipped', $conversation->rootMessage?->template);
        $this->assertTrue($conversation->unread);
        $this->assertFalse($conversation->archived);
        $this->assertSame(1, $conversation->inboundCount);
        $this->assertSame('Where is my parcel?', $conversation->preview);
        $this->assertSame([], $conversation->items);
    }

    public function test_get_returns_the_thread(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->conversation([
                'root_message' => null,
                'items' => [
                    ['type' => 'inbound', 'at' => '2026-09-28T10:15:00+00:00', 'reply' => ['uuid' => 'r-1', 'stripped_text' => 'Hi', 'attachments' => []]],
                    ['type' => 'outbound', 'at' => '2026-09-28T11:00:00+00:00', 'message' => ['uuid' => 'm-2', 'status' => 'sent', 'events' => []]],
                ],
            ])]),
        ], $history);

        $conversation = $client->conversations()->get('c-1');

        $this->assertSame('/api/v1/conversations/c-1', $history[0]['request']->getUri()->getPath());
        $this->assertNull($conversation->rootMessage);
        $this->assertCount(2, $conversation->items);
        $this->assertTrue($conversation->items[0]->isInbound());
        $this->assertSame('r-1', $conversation->items[0]->reply?->uuid);
        $this->assertSame('m-2', $conversation->items[1]->message?->uuid);
        $this->assertCount(1, $conversation->replies());
    }

    public function test_state_helpers_patch_only_what_they_change(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->conversation(['unread' => false, 'read_by' => ['id' => 7, 'name' => 'Ana']])]),
            $this->jsonResponse(200, ['data' => $this->conversation(['archived' => true])]),
            $this->jsonResponse(200, ['data' => $this->conversation()]),
        ], $history);

        $read = $client->conversations()->markRead('c-1');
        $client->conversations()->archive('c-1');
        $client->conversations()->update('c-1', read: false, archived: false);

        $this->assertSame('PATCH', $history[0]['request']->getMethod());
        $this->assertSame('/api/v1/conversations/c-1', $history[0]['request']->getUri()->getPath());
        $this->assertSame(['read' => true], json_decode((string) $history[0]['request']->getBody(), true));
        $this->assertSame(['archived' => true], json_decode((string) $history[1]['request']->getBody(), true));
        $this->assertSame(['read' => false, 'archived' => false], json_decode((string) $history[2]['request']->getBody(), true));
        $this->assertFalse($read->unread);
        $this->assertSame(['id' => 7, 'name' => 'Ana'], $read->readBy);
    }

    public function test_an_unknown_conversation_is_a_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'Not found.', 'code' => 'conversation_not_found']),
        ], $history);

        try {
            $client->conversations()->get('nope');
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $exception) {
            $this->assertSame('conversation_not_found', $exception->getErrorCode());
        }
    }
}
