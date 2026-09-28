<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\CampaignReadinessCheck;
use Recado\Sdk\Dto\Message;
use Recado\Sdk\Dto\SendingDomainHealth;

/**
 * DTO fields the API already returns that the SDK used to drop.
 */
final class DtoParityTest extends TestCase
{
    public function test_message_exposes_the_delivering_provider_and_attachment_metadata(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'uuid' => 'm-1',
                'status' => 'delivered',
                'provider' => ['type' => 'ses', 'stream' => 'transactional'],
                'attachments' => [
                    ['filename' => 'invoice.pdf', 'content_type' => 'application/pdf', 'size' => 20480],
                ],
            ]]),
        ], $history);

        $message = $client->messages()->get('m-1');

        $this->assertNotNull($message->provider);
        $this->assertSame('ses', $message->provider->type);
        $this->assertSame('transactional', $message->provider->stream);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('invoice.pdf', $message->attachments[0]->filename);
        $this->assertSame('application/pdf', $message->attachments[0]->contentType);
        $this->assertSame(20480, $message->attachments[0]->size);
    }

    public function test_platform_delivery_and_an_older_api_leave_provider_null(): void
    {
        $message = Message::fromArray(['uuid' => 'm-1', 'provider' => null, 'attachments' => []]);
        $older = Message::fromArray(['uuid' => 'm-2']);

        $this->assertNull($message->provider);
        $this->assertSame([], $message->attachments);
        $this->assertNull($older->provider);
        $this->assertSame([], $older->attachments);
    }

    public function test_sending_domain_health_carries_the_breaker_judgement(): void
    {
        $health = SendingDomainHealth::fromArray([
            'id' => 12,
            'domain' => 'example.com',
            'health' => 'warning',
            'bounces' => 33,
            'complaints' => 1,
            'min_complaints' => 3,
            'bounce_judged' => true,
            'complaint_judged' => false,
            'bounce_over_limit' => false,
            'complaint_over_limit' => false,
            'over_limit' => true,
            'over_limit_window_hours' => 1,
        ]);

        $this->assertSame(33, $health->bounces);
        $this->assertSame(1, $health->complaints);
        $this->assertSame(3, $health->minComplaints);
        $this->assertTrue($health->bounceJudged);
        $this->assertFalse($health->complaintJudged);
        $this->assertFalse($health->bounceOverLimit);
        $this->assertFalse($health->complaintOverLimit);
        $this->assertTrue($health->overLimit);
        $this->assertTrue($health->isOverLimit());
        $this->assertSame(1, $health->overLimitWindowHours);
    }

    public function test_sending_domain_health_from_an_older_api_reports_null_judgement(): void
    {
        $health = SendingDomainHealth::fromArray(['id' => 12, 'health' => 'healthy', 'over_limit_window_hours' => null]);

        $this->assertNull($health->bounces);
        $this->assertNull($health->overLimit);
        $this->assertNull($health->overLimitWindowHours);
        $this->assertFalse($health->isOverLimit());
    }

    public function test_readiness_checks_expose_required_and_meta(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'ready' => true,
                'schedulable' => true,
                'recipients_total' => 4102,
                'checks' => [
                    ['key' => 'subject', 'passed' => true, 'code' => null, 'message' => null, 'meta' => [], 'required' => true],
                    [
                        'key' => 'estimated_cost',
                        'passed' => true,
                        'code' => null,
                        'message' => null,
                        'meta' => ['amount' => '0.410200', 'currency' => 'usd', 'upper_bound' => true],
                        'required' => false,
                    ],
                ],
            ]]),
        ], $history);

        $readiness = $client->campaigns()->readiness(42);

        $this->assertTrue($readiness->checks[0]->required);
        $this->assertFalse($readiness->checks[0]->isAdvisory());
        $this->assertFalse($readiness->checks[1]->required);
        $this->assertTrue($readiness->checks[1]->isAdvisory());
        $this->assertSame('0.410200', $readiness->checks[1]->metaValue('amount'));
        $this->assertSame('fallback', $readiness->checks[1]->metaValue('missing', 'fallback'));
    }

    public function test_without_required_the_known_advisory_keys_are_the_fallback(): void
    {
        $cost = CampaignReadinessCheck::fromArray(['key' => 'estimated_cost', 'passed' => true]);
        $subject = CampaignReadinessCheck::fromArray(['key' => 'subject', 'passed' => true]);
        // A failing warmup check is the breaker pause: it blocks.
        $pausedWarmup = CampaignReadinessCheck::fromArray([
            'key' => 'warmup',
            'passed' => false,
            'code' => 'warmup_paused_by_breaker',
        ]);

        $this->assertNull($cost->required);
        $this->assertTrue($cost->isAdvisory());
        $this->assertFalse($subject->isAdvisory());
        $this->assertFalse($pausedWarmup->isAdvisory());
    }
}
