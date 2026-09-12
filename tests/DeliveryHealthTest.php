<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\ValidationException;

/**
 * GET /delivery/health — the machine-readable twin of the Delivery page.
 */
final class DeliveryHealthTest extends TestCase
{
    public function test_health_maps_the_domains_suppressions_and_ses_block(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'domains' => [
                    $this->domain(),
                    $this->domain([
                        'id' => 13,
                        'domain' => 'paused.example.com',
                        'health' => 'paused',
                        'breaker' => [
                            'tripped_at' => '2026-09-11T08:00:00+00:00',
                            'reason' => ['bounce_rate' => 0.09],
                        ],
                    ]),
                ],
                'suppressions' => [
                    'global' => 31, 'scoped' => 4,
                    'contacts_bounced' => 28, 'contacts_complained' => 3,
                ],
                'ses_account' => [
                    'available' => true,
                    'sent_last_24h' => 12043,
                    'max_24h' => 50000,
                    'max_send_rate' => 14,
                    'sending_enabled' => true,
                    'enforcement_status' => 'HEALTHY',
                ],
            ]]),
        ], $history);

        $health = $client->delivery()->health();

        $this->assertCount(2, $health->domains);
        $this->assertSame('warning', $health->domains[0]->health);
        $this->assertSame(0.0402, $health->domains[0]->bounceRate);
        $this->assertSame(['status' => 'active', 'day_number' => 4], $health->domains[0]->warmup);
        $this->assertNull($health->domains[0]->breaker);

        // The traffic light is what a caller acts on, so it gets a helper.
        $paused = $health->pausedDomains();
        $this->assertCount(1, $paused);
        $this->assertSame('paused.example.com', $paused[0]->domain);
        $this->assertSame('2026-09-11T08:00:00+00:00', $paused[0]->breaker['tripped_at']);

        // `global` is this project's own contacts on the shared list, never the
        // platform-wide total.
        $this->assertSame(31, $health->suppressions->global);
        $this->assertSame(3, $health->suppressions->contactsComplained);

        $this->assertTrue($health->sesAccount->available);
        $this->assertSame(50000.0, $health->sesAccount->max24h);
        $this->assertSame('HEALTHY', $health->sesAccount->enforcementStatus);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/delivery/health', $request->getUri()->getPath());
    }

    public function test_a_null_ses_block_means_no_byo_ses_and_an_unavailable_one_means_unreadable(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'domains' => [], 'suppressions' => [], 'ses_account' => null,
            ]]),
            $this->jsonResponse(200, ['data' => [
                'domains' => [], 'suppressions' => [], 'ses_account' => ['available' => false],
            ]]),
        ], $history);

        // Absent: the project has no BYO SES provider at all.
        $this->assertNull($client->delivery()->health()->sesAccount);

        // Present but unreadable: a different answer, and deliberately not
        // collapsed into the first one.
        $degraded = $client->delivery()->health()->sesAccount;
        $this->assertFalse($degraded->available);
        $this->assertNull($degraded->max24h);
    }

    public function test_a_sandbox_token_is_refused_with_its_own_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'Sender health is not available in a sandbox.',
                'code' => 'not_available_in_sandbox',
            ]),
        ], $history);

        try {
            $client->delivery()->health();
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('not_available_in_sandbox', $e->getErrorCode());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function domain(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }
}
