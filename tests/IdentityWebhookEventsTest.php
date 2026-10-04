<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use LogicException;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Recado\Sdk\Webhooks\WebhookEvent;
use Recado\Sdk\Webhooks\WebhookPayload;

/**
 * The typed views of the sending-identity webhooks: a breaker pause, its
 * resume and the transactional reputation alert.
 */
final class IdentityWebhookEventsTest extends BaseTestCase
{
    public function test_breaker_tripped_is_typed_and_says_whether_it_resumes_by_itself(): void
    {
        $payload = $this->payload('identity.breaker_tripped', [
            'domain' => 'acme.com',
            'rates' => ['bounce_rate' => 0.0658, 'complaint_rate' => 0],
            'sample' => 76,
            'thresholds' => ['bounce_rate' => 0.05, 'complaint_rate' => 0.001],
            'window_hours' => 24,
            'auto_resume' => ['eligible' => true, 'at' => '2026-10-05T10:58:02+00:00', 'reason' => null],
        ]);

        $this->assertSame(WebhookEvent::IdentityBreakerTripped, $payload->type());

        $trip = $payload->identityBreakerTripped();

        $this->assertSame('acme.com', $trip->domain);
        $this->assertSame(0.0658, $trip->bounceRate);
        $this->assertSame(0.0, $trip->complaintRate);
        $this->assertSame(76, $trip->sample);
        $this->assertSame(0.05, $trip->bounceThreshold);
        $this->assertSame(0.001, $trip->complaintThreshold);
        $this->assertSame(24, $trip->windowHours);
        $this->assertTrue($trip->willResumeAutomatically());
        $this->assertSame('2026-10-05T10:58:02+00:00', $trip->autoResume?->at);
        $this->assertSame($payload->data, $trip->raw);
    }

    public function test_a_trip_that_needs_a_person_carries_the_reason(): void
    {
        $trip = $this->payload('identity.breaker_tripped', [
            'domain' => 'acme.com',
            'auto_resume' => ['eligible' => false, 'at' => null, 'reason' => 'auto_resume_used'],
        ])->identityBreakerTripped();

        $this->assertFalse($trip->willResumeAutomatically());
        $this->assertSame('auto_resume_used', $trip->autoResume?->reason);

        // A server that predates automatic resume sends no verdict at all.
        $older = $this->payload('identity.breaker_tripped', ['domain' => 'acme.com'])->identityBreakerTripped();
        $this->assertNull($older->autoResume);
        $this->assertFalse($older->willResumeAutomatically());
    }

    public function test_breaker_resumed_is_typed(): void
    {
        $resume = $this->payload('identity.breaker_resumed', [
            'domain' => 'acme.com',
            'resumed_by' => 'auto',
            'actor' => 'system',
            'tripped_at' => '2026-10-04T10:58:02+00:00',
            'resumed_at' => '2026-10-05T11:00:04+00:00',
            'restored_status' => 'completed',
            'resumed_campaigns' => 1,
            'resumed_automation_sends' => 12,
        ])->identityBreakerResumed();

        $this->assertSame('acme.com', $resume->domain);
        $this->assertTrue($resume->isAutomatic());
        $this->assertSame('system', $resume->actor);
        $this->assertSame('2026-10-04T10:58:02+00:00', $resume->trippedAt);
        $this->assertSame('2026-10-05T11:00:04+00:00', $resume->resumedAt);
        $this->assertSame('completed', $resume->restoredStatus);
        $this->assertSame(1, $resume->resumedCampaigns);
        $this->assertSame(12, $resume->resumedAutomationSends);

        $manual = $this->payload('identity.breaker_resumed', ['resumed_by' => 'manual', 'actor' => 'user'])
            ->identityBreakerResumed();
        $this->assertFalse($manual->isAutomatic());
        $this->assertSame(0, $manual->resumedCampaigns);
    }

    public function test_transactional_alert_is_typed_and_never_a_pause(): void
    {
        $alert = $this->payload('identity.transactional_alert', [
            'domain' => 'acme.com',
            'level' => 'warning',
            'traffic' => 'transactional',
            'metric' => 'bounce',
            'paused' => false,
            'rates' => ['bounce_rate' => 0.0658, 'complaint_rate' => 0],
            'sample' => 76,
            'bounces' => 5,
            'complaints' => 0,
            'thresholds' => ['bounce_warning' => 0.05, 'bounce_critical' => 0.08, 'complaint_rate' => 0.001],
            'window_hours' => 24,
        ])->identityTransactionalAlert();

        $this->assertSame('acme.com', $alert->domain);
        $this->assertSame('warning', $alert->level);
        $this->assertFalse($alert->isCritical());
        $this->assertSame('transactional', $alert->traffic);
        $this->assertSame('bounce', $alert->metric);
        $this->assertFalse($alert->paused);
        $this->assertSame(0.0658, $alert->bounceRate);
        $this->assertSame(0.0, $alert->complaintRate);
        $this->assertSame(76, $alert->sample);
        $this->assertSame(5, $alert->bounces);
        $this->assertSame(0, $alert->complaints);
        $this->assertSame(0.05, $alert->bounceWarningThreshold);
        $this->assertSame(0.08, $alert->bounceCriticalThreshold);
        $this->assertSame(0.001, $alert->complaintThreshold);
        $this->assertSame(24, $alert->windowHours);
    }

    public function test_a_typed_view_refuses_another_event(): void
    {
        $payload = $this->payload('identity.breaker_resumed', ['domain' => 'acme.com']);

        foreach ([
            static fn () => $payload->identityBreakerTripped(),
            static fn () => $payload->identityTransactionalAlert(),
            static fn () => $payload->messageReplied(),
        ] as $view) {
            try {
                $view();
                $this->fail('Expected a LogicException.');
            } catch (LogicException $e) {
                $this->assertStringContainsString('identity.breaker_resumed', $e->getMessage());
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payload(string $event, array $data): WebhookPayload
    {
        return WebhookPayload::fromArray([
            'event' => $event,
            'timestamp' => '2026-10-04T10:58:02+00:00',
            'sandbox' => false,
            'project' => ['uuid' => '0190f3a2-7c11-7e5d-9c1a-3b8d2f6a4e10'],
            'data' => $data,
        ]);
    }
}
