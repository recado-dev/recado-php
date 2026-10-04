<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\BreakerAutoResume;
use Recado\Sdk\Dto\SendingDomain;
use Recado\Sdk\Dto\SendingDomainHealth;

/**
 * The circuit-breaker additions of GET /delivery/health: the bounce floor,
 * the transactional (non-marketing) block, the resume floor, the breaker
 * history and the automatic-resume verdict.
 */
final class DeliveryBreakerHealthTest extends TestCase
{
    public function test_a_domain_block_carries_the_transactional_pressure_and_the_breaker_history(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'domains' => [$this->documentedDomain()],
                'suppressions' => [],
                'ses_account' => null,
            ]]),
        ], $history);

        $domain = $client->delivery()->health()->domains[0];

        $this->assertSame(5, $domain->minBounces);
        $this->assertNull($domain->breakerResumedAt);
        // Not paused: no pause to resume.
        $this->assertNull($domain->autoResume());

        $transactional = $domain->nonMarketing;
        $this->assertNotNull($transactional);
        $this->assertSame(1240, $transactional->sample);
        $this->assertSame(6, $transactional->bounces);
        $this->assertSame(0, $transactional->complaints);
        $this->assertSame(0.0048, $transactional->bounceRate);
        $this->assertSame(0.0, $transactional->complaintRate);
        $this->assertNull($transactional->alertLevel);
        $this->assertFalse($transactional->isAlerting());
        $this->assertSame(
            ['bounce_warning' => 0.05, 'bounce_critical' => 0.08, 'complaint' => 0.001],
            $transactional->alertThresholds,
        );
        $this->assertNull($transactional->alertedAt);

        $this->assertCount(2, $domain->history);

        $resume = $domain->history[0];
        $this->assertSame(42, $resume->id);
        $this->assertSame('manual_resume', $resume->type);
        $this->assertSame('2026-10-04T11:24:36+00:00', $resume->occurredAt);
        $this->assertSame('marketing', $resume->traffic);
        $this->assertSame('user', $resume->actorType);
        $this->assertTrue($resume->isResume());
        $this->assertFalse($resume->isAutomaticResume());
        $this->assertNull($resume->evaluation);
        $this->assertSame('completed', $resume->resume['restored_status']);
        $this->assertSame(12, $resume->resume['resumed_automation_sends']);
        $this->assertNull($resume->level);
        $this->assertNull($resume->autoResume);

        $trip = $domain->history[1];
        $this->assertTrue($trip->isTrip());
        $this->assertSame('system', $trip->actorType);
        $this->assertSame('bounce', $trip->evaluation['metric']);
        $this->assertSame(0.0658, $trip->evaluation['bounce_rate']);
        $this->assertNull($trip->resume);
        $this->assertNotNull($trip->autoResume);
        $this->assertTrue($trip->autoResume->eligible);
        $this->assertSame('2026-10-05T10:58:02+00:00', $trip->autoResume->at);
    }

    public function test_a_paused_identity_says_whether_the_pause_lifts_by_itself(): void
    {
        $eligible = SendingDomainHealth::fromArray([
            'health' => 'paused',
            'breaker' => [
                'tripped_at' => '2026-10-04T10:58:02+00:00',
                'reason' => ['sample' => 76, 'bounce_rate' => 0.0658],
                'auto_resume' => ['eligible' => true, 'at' => '2026-10-05T10:58:02+00:00', 'reason' => null],
            ],
            'breaker_resumed_at' => '2026-10-03T09:00:00+00:00',
        ]);

        $this->assertSame('2026-10-03T09:00:00+00:00', $eligible->breakerResumedAt);
        $this->assertTrue($eligible->autoResume()?->eligible);
        $this->assertFalse($eligible->autoResume()->needsManualResume());
        $this->assertSame('2026-10-05T10:58:02+00:00', $eligible->autoResume()->at);

        $manual = SendingDomainHealth::fromArray([
            'health' => 'paused',
            'breaker' => [
                'tripped_at' => '2026-10-04T10:58:02+00:00',
                'reason' => [],
                'auto_resume' => ['eligible' => false, 'at' => null, 'reason' => 'complaints'],
            ],
        ]);

        $this->assertTrue($manual->autoResume()?->needsManualResume());
        $this->assertNull($manual->autoResume()->at);
        $this->assertSame('complaints', $manual->autoResume()->reason);
    }

    public function test_alert_and_automatic_resume_history_events_are_typed(): void
    {
        $domain = SendingDomainHealth::fromArray([
            'non_marketing' => [
                'sample' => 76, 'bounces' => 5, 'complaints' => 0,
                'bounce_rate' => 0.0658, 'complaint_rate' => 0,
                'alert_level' => 'critical',
                'alert_thresholds' => ['bounce_warning' => 0.05, 'bounce_critical' => 0.06, 'complaint' => 0.001],
                'alerted_at' => '2026-10-04T11:30:04+00:00',
            ],
            'history' => [
                [
                    'id' => 44, 'type' => 'auto_resume', 'occurred_at' => '2026-10-05T11:00:04+00:00',
                    'traffic' => 'marketing', 'actor' => ['type' => 'system'], 'evaluation' => null,
                    'resume' => ['tripped_at' => '2026-10-04T10:58:02+00:00', 'restored_status' => 'completed', 'resumed_campaigns' => 1, 'resumed_automation_sends' => 0],
                    'level' => null, 'auto_resume' => null,
                ],
                [
                    'id' => 43, 'type' => 'alert', 'occurred_at' => '2026-10-04T11:30:04+00:00',
                    'traffic' => 'transactional', 'actor' => ['type' => 'system'],
                    'evaluation' => ['metric' => 'bounce', 'window_hours' => 24, 'sample' => 76, 'bounces' => 5, 'complaints' => 0, 'bounce_rate' => 0.0658, 'complaint_rate' => 0, 'bounce_threshold' => 0.06, 'complaint_threshold' => 0.001],
                    'resume' => null, 'level' => 'critical', 'auto_resume' => null,
                ],
            ],
        ]);

        $this->assertTrue($domain->nonMarketing?->isAlerting());
        $this->assertTrue($domain->nonMarketing->isCritical());
        $this->assertSame('2026-10-04T11:30:04+00:00', $domain->nonMarketing->alertedAt);

        $this->assertTrue($domain->history[0]->isResume());
        $this->assertTrue($domain->history[0]->isAutomaticResume());
        $this->assertSame(1, $domain->history[0]->resume['resumed_campaigns']);

        $this->assertTrue($domain->history[1]->isAlert());
        $this->assertFalse($domain->history[1]->isTrip());
        $this->assertSame('critical', $domain->history[1]->level);
        $this->assertSame('transactional', $domain->history[1]->traffic);
    }

    public function test_an_older_api_leaves_the_additions_empty(): void
    {
        $domain = SendingDomainHealth::fromArray([
            'id' => 12,
            'health' => 'paused',
            'breaker' => ['tripped_at' => '2026-09-11T08:00:00+00:00', 'reason' => []],
        ]);

        $this->assertNull($domain->minBounces);
        $this->assertNull($domain->nonMarketing);
        $this->assertNull($domain->breakerResumedAt);
        $this->assertSame([], $domain->history);
        $this->assertNull($domain->autoResume());
    }

    public function test_the_sending_domain_warmup_block_exposes_the_resume_verdict(): void
    {
        $paused = SendingDomain::fromArray([
            'id' => 12,
            'domain' => 'example.com',
            'status' => 'verified',
            'warmup' => [
                'status' => 'paused_breaker',
                'previous_status' => 'completed',
                'breaker_tripped_at' => '2026-10-04T10:58:02+00:00',
                'breaker_reason' => ['bounce_rate' => 0.0658],
                'auto_resume' => ['eligible' => false, 'at' => null, 'reason' => 'auto_resume_used'],
                'manual_resumes' => ['count' => 2, 'days' => 30],
            ],
        ]);

        $this->assertInstanceOf(BreakerAutoResume::class, $paused->autoResume());
        $this->assertSame('auto_resume_used', $paused->autoResume()->reason);
        $this->assertSame(2, $paused->manualResumeCount());

        $ramping = SendingDomain::fromArray([
            'warmup' => ['status' => 'active', 'auto_resume' => null, 'manual_resumes' => ['count' => 0, 'days' => 30]],
        ]);
        $this->assertNull($ramping->autoResume());
        $this->assertSame(0, $ramping->manualResumeCount());

        // No warm-up block, or an API that predates the keys.
        $this->assertNull(SendingDomain::fromArray(['warmup' => null])->autoResume());
        $this->assertNull(SendingDomain::fromArray(['warmup' => ['status' => 'active']])->manualResumeCount());
    }

    /**
     * The domain block of the documented GET /delivery/health example.
     *
     * @return array<string, mixed>
     */
    private function documentedDomain(): array
    {
        return [
            'id' => 12,
            'domain' => 'example.com',
            'health' => 'warning',
            'sample' => 820,
            'min_sample' => 50,
            'window_hours' => 24,
            'bounce_rate' => 0.0402,
            'complaint_rate' => 0.0004,
            'bounce_threshold' => 0.05,
            'complaint_threshold' => 0.001,
            'warning_ratio' => 0.7,
            'warmup' => ['status' => 'active', 'day_number' => 4],
            'breaker' => null,
            'bounces' => 33,
            'complaints' => 1,
            'min_complaints' => 3,
            'bounce_judged' => true,
            'complaint_judged' => false,
            'bounce_over_limit' => false,
            'complaint_over_limit' => false,
            'over_limit' => false,
            'over_limit_window_hours' => null,
            'min_bounces' => 5,
            'non_marketing' => [
                'sample' => 1240, 'bounces' => 6, 'complaints' => 0, 'bounce_rate' => 0.0048, 'complaint_rate' => 0,
                'alert_level' => null,
                'alert_thresholds' => ['bounce_warning' => 0.05, 'bounce_critical' => 0.08, 'complaint' => 0.001],
                'alerted_at' => null,
            ],
            'breaker_resumed_at' => null,
            'history' => [
                [
                    'id' => 42,
                    'type' => 'manual_resume',
                    'occurred_at' => '2026-10-04T11:24:36+00:00',
                    'traffic' => 'marketing',
                    'actor' => ['type' => 'user'],
                    'evaluation' => null,
                    'resume' => ['tripped_at' => '2026-10-04T10:58:02+00:00', 'restored_status' => 'completed', 'resumed_campaigns' => 1, 'resumed_automation_sends' => 12],
                    'level' => null,
                    'auto_resume' => null,
                ],
                [
                    'id' => 41,
                    'type' => 'trip',
                    'occurred_at' => '2026-10-04T10:58:02+00:00',
                    'traffic' => 'marketing',
                    'actor' => ['type' => 'system'],
                    'evaluation' => ['metric' => 'bounce', 'window_hours' => 24, 'sample' => 76, 'bounces' => 5, 'complaints' => 0, 'bounce_rate' => 0.0658, 'complaint_rate' => 0, 'bounce_threshold' => 0.05, 'complaint_threshold' => 0.001],
                    'resume' => null,
                    'level' => null,
                    'auto_resume' => ['eligible' => true, 'at' => '2026-10-05T10:58:02+00:00', 'reason' => null],
                ],
            ],
        ];
    }
}
