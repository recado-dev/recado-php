<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\TemplateResendTooSoonException;
use Recado\Sdk\Exception\ValidationException;

final class TemplateResendGuardTest extends TestCase
{
    public function test_template_exposes_the_resend_interval_and_editor(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'slug' => 'verify-email',
                'name' => 'Verify email',
                'subject' => 'Verify',
                'editor' => 'html',
                'min_resend_interval_minutes' => 10,
                'body_html' => '<p>Hi</p>',
                'body_text' => null,
                'variants' => [],
            ]]),
        ], $history);

        $template = $client->templates()->get('verify-email');

        $this->assertSame(10, $template->minResendIntervalMinutes);
        $this->assertSame('html', $template->editor);
    }

    public function test_a_template_without_the_guard_reports_null(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['slug' => 'welcome', 'min_resend_interval_minutes' => null]]),
        ], $history);

        $this->assertNull($client->templates()->get('welcome')->minResendIntervalMinutes);
    }

    public function test_create_and_update_forward_the_resend_interval(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => ['slug' => 'verify-email', 'min_resend_interval_minutes' => 15]]),
            $this->jsonResponse(200, ['data' => ['slug' => 'verify-email', 'min_resend_interval_minutes' => null]]),
        ], $history);

        $created = $client->templates()->create([
            'name' => 'Verify email',
            'slug' => 'verify-email',
            'subject' => 'Verify',
            'body_html' => '<p>Hi</p>',
            'min_resend_interval_minutes' => 15,
        ]);
        $updated = $client->templates()->update('verify-email', ['min_resend_interval_minutes' => null]);

        $this->assertSame(15, $created->minResendIntervalMinutes);
        $this->assertNull($updated->minResendIntervalMinutes);

        $createBody = json_decode((string) $history[0]['request']->getBody(), true);
        $updateBody = json_decode((string) $history[1]['request']->getBody(), true);
        $this->assertSame(15, $createBody['min_resend_interval_minutes']);
        $this->assertArrayHasKey('min_resend_interval_minutes', $updateBody);
        $this->assertNull($updateBody['min_resend_interval_minutes']);
    }

    public function test_a_refused_send_throws_a_typed_exception_with_the_retry_hint(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'This template was already sent to this contact less than 10 minutes ago.',
                'code' => 'template_resend_too_soon',
                'retry_after_seconds' => 412,
            ]),
        ], $history);

        try {
            $client->send()->email(['to' => 'ana@example.com', 'template' => 'verify-email']);
            $this->fail('Expected a TemplateResendTooSoonException.');
        } catch (TemplateResendTooSoonException $e) {
            // Still a ValidationException, so existing catch blocks keep working.
            $this->assertInstanceOf(ValidationException::class, $e);
            $this->assertSame('template_resend_too_soon', $e->getErrorCode());
            $this->assertSame(422, $e->getStatus());
            $this->assertSame(412, $e->retryAfterSeconds());
        }
    }

    public function test_other_422s_stay_plain_validation_exceptions(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, ['message' => 'Suppressed.', 'code' => 'recipient_suppressed']),
        ], $history);

        try {
            $client->send()->email(['to' => 'ana@example.com', 'template' => 'verify-email']);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertNotInstanceOf(TemplateResendTooSoonException::class, $e);
        }
    }

    public function test_a_batch_item_refused_by_the_guard_carries_the_retry_hint(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'messages' => [
                    ['index' => 0, 'status' => 'queued', 'id' => 'm-1'],
                    [
                        'index' => 1,
                        'status' => 'failed',
                        'code' => 'template_resend_too_soon',
                        'error' => 'Too soon.',
                        'retry_after_seconds' => 598,
                    ],
                ],
                'queued' => 1,
                'failed' => 1,
            ]]),
        ], $history);

        $result = $client->send()->batch([
            ['to' => 'ana@example.com', 'template' => 'verify-email'],
            ['to' => 'ana@example.com', 'template' => 'verify-email'],
        ]);

        $this->assertNull($result->messages[0]->retryAfterSeconds);
        $this->assertSame('template_resend_too_soon', $result->messages[1]->code);
        $this->assertSame(598, $result->messages[1]->retryAfterSeconds);
    }
}
