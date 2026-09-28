<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\InboundReply;
use Recado\Sdk\Exception\NotFoundException;

final class RepliesTest extends TestCase
{
    public function test_message_replies_are_paginated_and_typed(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->reply()],
                'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1],
                'links' => ['next' => null],
            ]),
        ], $history);

        $page = $client->messages()->replies('m-1', ['per_page' => 25]);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/messages/m-1/replies', $request->getUri()->getPath());
        $this->assertSame('per_page=25', $request->getUri()->getQuery());

        $this->assertCount(1, $page->data);
        $reply = $page->data[0];
        $this->assertInstanceOf(InboundReply::class, $reply);
        $this->assertSame('r-1', $reply->uuid);
        $this->assertSame('m-1', $reply->messageUuid);
        $this->assertSame('c-1', $reply->contactUuid);
        $this->assertSame('ana@example.org', $reply->contactEmail);
        $this->assertSame('Ana García', $reply->fromName);
        $this->assertSame('Re: Your order', $reply->subject);
        $this->assertSame('Thanks!', $reply->body());
        $this->assertSame('2026-09-28T10:15:00+00:00', $reply->receivedAt);
        $this->assertSame(['spf' => 'PASS', 'dkim' => 'PASS', 'dmarc' => 'PASS'], $reply->auth);
        $this->assertTrue($reply->authenticated);
        $this->assertFalse($reply->autoReply);
        $this->assertFalse($reply->bounceReport);
        $this->assertFalse($reply->unparsable);
        $this->assertTrue($reply->isGenuine());
        $this->assertCount(2, $reply->attachments);
        $this->assertNull($reply->attachments[0]->withheld);
        $this->assertSame('executable', $reply->attachments[1]->withheld);
    }

    public function test_contact_replies_hit_the_contact_endpoint_and_flag_auto_replies(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->reply([
                    'message_uuid' => null,
                    'stripped_text' => null,
                    'auto_reply' => true,
                    'attachments' => [],
                ])],
                'meta' => ['current_page' => 1, 'total' => 1],
                'links' => [],
            ]),
        ], $history);

        $reply = $client->contacts()->replies('ana@example.org')->data[0];

        $this->assertSame('/api/v1/contacts/ana%40example.org/replies', $history[0]['request']->getUri()->getPath());
        $this->assertNull($reply->messageUuid);
        $this->assertSame("Thanks!\n\n> quoted", $reply->body());
        $this->assertFalse($reply->isGenuine());
    }

    public function test_replies_cursor_walks_every_page(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->reply(['uuid' => 'r-1'])],
                'meta' => ['current_page' => 1, 'last_page' => 2],
                'links' => ['next' => 'https://recado.example.com/api/v1/messages/m-1/replies?page=2'],
            ]),
            $this->jsonResponse(200, [
                'data' => [$this->reply(['uuid' => 'r-2'])],
                'meta' => ['current_page' => 2, 'last_page' => 2],
                'links' => ['next' => null],
            ]),
        ], $history);

        $uuids = [];
        foreach ($client->messages()->repliesCursor('m-1') as $reply) {
            $uuids[] = $reply->uuid;
        }

        $this->assertSame(['r-1', 'r-2'], $uuids);
    }

    public function test_an_unknown_message_is_a_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'Not found.', 'code' => 'message_not_found']),
        ], $history);

        try {
            $client->messages()->replies('nope');
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('message_not_found', $e->getErrorCode());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reply(array $overrides = []): array
    {
        return array_merge([
            'uuid' => 'r-1',
            'message_uuid' => 'm-1',
            'contact' => ['uuid' => 'c-1', 'email' => 'ana@example.org'],
            'from_email' => 'ana@example.org',
            'from_name' => 'Ana García',
            'subject' => 'Re: Your order',
            'stripped_text' => 'Thanks!',
            'text' => "Thanks!\n\n> quoted",
            'received_at' => '2026-09-28T10:15:00+00:00',
            'auth' => ['spf' => 'PASS', 'dkim' => 'PASS', 'dmarc' => 'PASS'],
            'authenticated' => true,
            'auto_reply' => false,
            'bounce_report' => false,
            'truncated' => false,
            'unparsable' => false,
            'attachments' => [
                ['filename' => 'photo.png', 'content_type' => 'image/png', 'size' => 1200, 'withheld' => null],
                ['filename' => 'run.exe', 'content_type' => 'application/octet-stream', 'size' => 900, 'withheld' => 'executable'],
            ],
        ], $overrides);
    }
}
