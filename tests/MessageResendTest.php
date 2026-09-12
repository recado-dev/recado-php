<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\ValidationException;

/**
 * POST /messages/{uuid}/resend — the end of the support flow that starts with
 * messages()->get(): the customer says the email never arrived.
 */
final class MessageResendTest extends TestCase
{
    public function test_resend_posts_and_returns_the_new_queued_message(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'uuid' => '5f0d1c20-6f5e-4b8a-9d7c-1e2f3a4b5c6d',
                'to_email' => 'jane@acme.com',
                'subject' => 'Your receipt',
                'status' => 'queued',
            ]]),
        ], $history);

        $message = $client->messages()->resend('11111111-2222-3333-4444-555555555555');

        // A resend is a BRAND-NEW message: the original row is never touched,
        // so the uuid that comes back is not the one we asked for.
        $this->assertSame('5f0d1c20-6f5e-4b8a-9d7c-1e2f3a4b5c6d', $message->uuid);
        $this->assertSame('queued', $message->status);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(
            '/api/v1/messages/11111111-2222-3333-4444-555555555555/resend',
            $request->getUri()->getPath(),
        );
    }

    public function test_a_non_resendable_message_keeps_its_machine_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'This message cannot be resent.',
                'code' => 'message_not_resendable',
            ]),
        ], $history);

        try {
            $client->messages()->resend('11111111-2222-3333-4444-555555555555');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            // Campaign sends, click-tracked messages, non-email messages and
            // still-queued ones all land here.
            $this->assertSame('message_not_resendable', $e->getErrorCode());
        }
    }

    public function test_an_unknown_uuid_is_a_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'Message not found.', 'code' => 'message_not_found']),
        ], $history);

        try {
            $client->messages()->resend('00000000-0000-0000-0000-000000000000');
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('message_not_found', $e->getErrorCode());
        }
    }
}
