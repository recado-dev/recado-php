<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use LogicException;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Recado\Sdk\Exception\RecadoException;
use Recado\Sdk\Exception\WebhookVerificationException;
use Recado\Sdk\Webhooks\WebhookEvent;
use Recado\Sdk\Webhooks\WebhookPayload;
use Recado\Sdk\Webhooks\WebhookSignature;

final class WebhookSignatureTest extends BaseTestCase
{
    private const string SECRET = 'whsec_test_secret';

    public function test_compute_is_the_hex_hmac_sha256_of_the_raw_body(): void
    {
        $body = '{"event":"ping"}';

        $this->assertSame(hash_hmac('sha256', $body, self::SECRET), WebhookSignature::compute($body, self::SECRET));
    }

    public function test_a_valid_signature_passes_case_and_whitespace_insensitively(): void
    {
        $body = $this->repliedBody();
        $signature = hash_hmac('sha256', $body, self::SECRET);

        $this->assertTrue(WebhookSignature::isValid($body, $signature, self::SECRET));
        $this->assertTrue(WebhookSignature::isValid($body, ' '.strtoupper($signature)."\n", self::SECRET));
    }

    public function test_a_tampered_body_a_wrong_secret_or_a_missing_signature_fail(): void
    {
        $body = $this->repliedBody();
        $signature = hash_hmac('sha256', $body, self::SECRET);

        $this->assertFalse(WebhookSignature::isValid($body.' ', $signature, self::SECRET));
        $this->assertFalse(WebhookSignature::isValid($body, $signature, 'whsec_other'));
        $this->assertFalse(WebhookSignature::isValid($body, null, self::SECRET));
        $this->assertFalse(WebhookSignature::isValid($body, '', self::SECRET));
        $this->assertFalse(WebhookSignature::isValid($body, $signature, ''));
    }

    public function test_construct_event_refuses_a_bad_signature(): void
    {
        try {
            WebhookPayload::constructEvent($this->repliedBody(), 'deadbeef', self::SECRET);
            $this->fail('Expected a WebhookVerificationException.');
        } catch (WebhookVerificationException $e) {
            $this->assertInstanceOf(RecadoException::class, $e);
            $this->assertSame(WebhookVerificationException::INVALID_SIGNATURE, $e->getErrorCode());
        }
    }

    public function test_construct_event_refuses_a_signed_body_that_is_not_an_envelope(): void
    {
        $body = '"just a string"';

        $this->expectException(WebhookVerificationException::class);

        WebhookPayload::constructEvent($body, hash_hmac('sha256', $body, self::SECRET), self::SECRET);
    }

    public function test_generic_envelope_access_works_for_any_event(): void
    {
        $body = json_encode([
            'event' => 'contact.list_confirmed',
            'timestamp' => '2026-09-28T10:00:00+00:00',
            'project' => ['uuid' => 'p-1'],
            'sandbox' => true,
            'data' => ['list' => ['id' => 12, 'name' => 'Beta'], 'confirmed_at' => '2026-09-28T10:00:00+00:00'],
        ], JSON_THROW_ON_ERROR);

        $payload = WebhookPayload::constructEvent($body, hash_hmac('sha256', $body, self::SECRET), self::SECRET);

        $this->assertSame('contact.list_confirmed', $payload->event);
        $this->assertSame(WebhookEvent::ContactListConfirmed, $payload->type());
        $this->assertTrue($payload->is(WebhookEvent::ContactListConfirmed));
        $this->assertTrue($payload->sandbox);
        $this->assertSame('p-1', $payload->projectUuid);
        $this->assertSame(12, $payload->data['list']['id']);

        $this->expectException(LogicException::class);
        $payload->messageReplied();
    }

    public function test_an_unknown_event_still_parses(): void
    {
        $payload = WebhookPayload::fromJson('{"event":"ping","project":{"uuid":"p-1"},"data":{}}');

        $this->assertNull($payload->type());
        $this->assertFalse($payload->sandbox);
    }

    public function test_message_replied_is_typed(): void
    {
        $body = $this->repliedBody();
        $payload = WebhookPayload::constructEvent($body, hash_hmac('sha256', $body, self::SECRET), self::SECRET);

        $reply = $payload->messageReplied();

        $this->assertSame('5c0e', $reply->inboundUuid);
        $this->assertSame('ana@example.org', $reply->fromEmail);
        $this->assertSame('Ana García', $reply->fromName);
        $this->assertSame('Re: Your cancellation is scheduled', $reply->subject);
        $this->assertSame('Hi, I want to keep my plan after all.', $reply->body());
        $this->assertFalse($reply->truncated);
        $this->assertCount(1, $reply->attachments);
        $this->assertSame('image/png', $reply->attachments[0]->contentType);
        $this->assertSame(48213, $reply->attachments[0]->size);
        $this->assertSame(['spf' => 'PASS', 'dkim' => 'PASS', 'dmarc' => 'PASS'], $reply->auth);
        $this->assertSame('3f2a', $reply->messageUuid);
        $this->assertSame('api', $reply->messageSource);
        $this->assertSame('cancel-followup', $reply->messageTemplate);
        $this->assertNull($reply->messageCampaignId);
        $this->assertSame(['subscription_id' => 'sub_123'], $reply->messageMetadata);
        $this->assertSame('c-1', $reply->contactUuid);
        $this->assertSame('ana@example.org', $reply->contactEmail);
        $this->assertSame('cv-1', $reply->conversationUuid);
        $this->assertTrue($reply->conversationUnread);
        $this->assertFalse($reply->conversationArchived);
    }

    public function test_message_replied_tolerates_a_deleted_message_and_an_unknown_sender(): void
    {
        $payload = WebhookPayload::fromArray([
            'event' => 'message.replied',
            'data' => [
                'inbound' => ['uuid' => 'r-1', 'text' => 'Full text', 'stripped_text' => '', 'truncated' => true],
                'message' => null,
                'contact' => null,
            ],
        ]);

        $reply = $payload->messageReplied();

        $this->assertNull($reply->messageUuid);
        $this->assertNull($reply->messageMetadata);
        $this->assertNull($reply->contactUuid);
        // A payload from a server that predates the Inbox.
        $this->assertNull($reply->conversationUuid);
        $this->assertNull($reply->conversationUnread);
        $this->assertTrue($reply->truncated);
        $this->assertSame('Full text', $reply->body());
        $this->assertSame(['spf' => null, 'dkim' => null, 'dmarc' => null], $reply->auth);
    }

    private function repliedBody(): string
    {
        return json_encode([
            'event' => 'message.replied',
            'timestamp' => '2026-09-28T10:15:03+00:00',
            'project' => ['uuid' => 'p-1'],
            'sandbox' => false,
            'data' => [
                'inbound' => [
                    'uuid' => '5c0e',
                    'received_at' => '2026-09-28T10:15:00+00:00',
                    'from_email' => 'ana@example.org',
                    'from_name' => 'Ana García',
                    'subject' => 'Re: Your cancellation is scheduled',
                    'text' => "Hi, I want to keep my plan after all.\n\n> quoted",
                    'stripped_text' => 'Hi, I want to keep my plan after all.',
                    'truncated' => false,
                    'attachments' => [['filename' => 'screenshot.png', 'content_type' => 'image/png', 'size' => 48213]],
                    'auth' => ['spf' => 'PASS', 'dkim' => 'PASS', 'dmarc' => 'PASS'],
                ],
                'message' => [
                    'uuid' => '3f2a',
                    'source' => 'api',
                    'template' => 'cancel-followup',
                    'campaign_id' => null,
                    'automation_id' => null,
                    'metadata' => ['subscription_id' => 'sub_123'],
                    'sent_at' => '2026-09-28T09:00:02+00:00',
                ],
                'contact' => ['uuid' => 'c-1', 'email' => 'ana@example.org'],
                'conversation' => ['uuid' => 'cv-1', 'unread' => true, 'archived' => false],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
