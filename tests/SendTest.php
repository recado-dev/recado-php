<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\SentMessage;

final class SendTest extends TestCase
{
    public function test_email_returns_sent_message_and_posts_to_send(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['id' => '11111111-2222-3333-4444-555555555555', 'status' => 'queued'],
            ]),
        ], $history);

        $result = $client->send()->email([
            'to' => 'jane@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
        ]);

        $this->assertInstanceOf(SentMessage::class, $result);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $result->id);
        $this->assertSame('queued', $result->status);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/send', $request->getUri()->getPath());
        $this->assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('jane@example.com', $body['to']);
        $this->assertSame('Hello', $body['subject']);
        $this->assertSame('<p>Hi</p>', $body['body']);
    }

    public function test_email_sends_idempotency_key_header(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'abc', 'status' => 'queued']]),
        ], $history);

        $client->send()->email(['to' => 'jane@example.com', 'template' => 'welcome'], idempotencyKey: 'abc');

        $request = $history[0]['request'];
        $this->assertSame('abc', $request->getHeaderLine('Idempotency-Key'));
    }

    public function test_email_without_idempotency_key_sends_no_header(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'abc', 'status' => 'queued']]),
        ], $history);

        $client->send()->email(['to' => 'jane@example.com', 'template' => 'welcome']);

        $request = $history[0]['request'];
        $this->assertFalse($request->hasHeader('Idempotency-Key'));
    }

    public function test_email_passes_send_options_through(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'abc', 'status' => 'queued']]),
        ], $history);

        $client->send()->email([
            'to' => 'jane@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
            'cc' => ['copy@example.com'],
            'bcc' => ['hidden@example.com'],
            'reply_to' => 'support@example.com',
            'from' => 'notify@verified.example.com',
            'from_name' => 'Acme',
            'headers' => ['X-Order-Id' => '42'],
            'metadata' => ['order_id' => 42],
        ]);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame(['copy@example.com'], $body['cc']);
        $this->assertSame(['hidden@example.com'], $body['bcc']);
        $this->assertSame('support@example.com', $body['reply_to']);
        $this->assertSame('notify@verified.example.com', $body['from']);
        $this->assertSame('Acme', $body['from_name']);
        $this->assertSame(['X-Order-Id' => '42'], $body['headers']);
        $this->assertSame(['order_id' => 42], $body['metadata']);
    }

    public function test_track_records_event(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['id' => 42, 'event' => 'order-placed', 'email' => 'jane@example.com'],
            ]),
        ], $history);

        $data = $client->send()->track('order.placed', 'jane@example.com', ['total' => 10]);

        $this->assertSame(42, $data['id']);
        $this->assertSame('order-placed', $data['event']);

        $request = $history[0]['request'];
        $this->assertSame('/api/v1/track', $request->getUri()->getPath());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('order.placed', $body['event']);
        $this->assertSame('jane@example.com', $body['email']);
        $this->assertSame(['total' => 10], $body['data']);
        $this->assertArrayNotHasKey('first_name', $body);
        $this->assertArrayNotHasKey('last_name', $body);
        $this->assertArrayNotHasKey('name', $body);
    }

    public function test_track_forwards_the_contact_name_fields(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['id' => 42, 'event' => 'order-placed', 'email' => 'jane@example.com'],
            ]),
        ], $history);

        $client->send()->track('order.placed', 'jane@example.com', ['total' => 10], [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('Jane', $body['first_name']);
        $this->assertSame('Doe', $body['last_name']);
        // The event payload stays where it belongs.
        $this->assertSame(['total' => 10], $body['data']);
    }

    public function test_track_forwards_a_full_name_without_an_event_payload(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['id' => 43, 'event' => 'signed-up', 'email' => 'ada@example.com'],
            ]),
        ], $history);

        // The API splits `name` on the first whitespace; explicit fields win
        // server-side, so the SDK simply passes both through.
        $client->send()->track('signed-up', 'ada@example.com', [], ['name' => 'Ada Lovelace']);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame(
            ['event' => 'signed-up', 'email' => 'ada@example.com', 'name' => 'Ada Lovelace'],
            $body,
        );
    }

    public function test_track_contact_fields_never_shadow_the_positional_args(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['id' => 44, 'event' => 'signed-up', 'email' => 'ada@example.com'],
            ]),
        ], $history);

        $client->send()->track('signed-up', 'ada@example.com', ['plan' => 'pro'], [
            // A caller mistake (or untrusted input) must never redirect the
            // call to another contact/event or replace the event payload.
            'email' => 'attacker@example.com',
            'event' => 'other-event',
            'data' => ['plan' => 'free'],
            'first_name' => 'Ada',
        ]);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('signed-up', $body['event']);
        $this->assertSame('ada@example.com', $body['email']);
        $this->assertSame(['plan' => 'pro'], $body['data']);
        $this->assertSame('Ada', $body['first_name']);
    }

    public function test_track_forwards_the_contact_locale(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['id' => 45, 'event' => 'signed-up', 'email' => 'ada@example.com'],
            ]),
        ], $history);

        $client->send()->track('signed-up', 'ada@example.com', [], [
            'name' => 'Ada Lovelace',
            'locale' => 'es-MX',
        ]);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('es-MX', $body['locale']);
    }

    public function test_track_forwards_lists_and_tags_at_the_top_level(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['id' => 46, 'event' => 'signed-up', 'email' => 'ada@example.com'],
            ]),
        ], $history);

        // The endpoint reads `lists`/`tags` next to `event`/`email`, not inside
        // the event payload — they must land at the TOP level of the body.
        $client->send()->track('signed-up', 'ada@example.com', ['plan' => 'pro'], [
            'lists' => [1],
            'tags' => ['beta'],
        ]);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame([1], $body['lists']);
        $this->assertSame(['beta'], $body['tags']);
        $this->assertSame(['plan' => 'pro'], $body['data']);
    }

    public function test_send_and_batch_pass_the_contact_name_fields_through(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'msg-1', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => [
                'messages' => [['index' => 0, 'status' => 'queued', 'id' => 'a']],
                'queued' => 1,
                'failed' => 0,
            ]]),
        ], $history);

        $client->send()->email([
            'to' => 'ada@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
            'name' => 'Ada Lovelace',
        ]);

        $client->send()->batch([[
            'to' => 'grace@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
        ]]);

        $single = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('Ada Lovelace', $single['name']);

        $item = json_decode((string) $history[1]['request']->getBody(), true)['messages'][0];
        $this->assertSame('Grace', $item['first_name']);
        $this->assertSame('Hopper', $item['last_name']);
    }
}
