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
    }
}
