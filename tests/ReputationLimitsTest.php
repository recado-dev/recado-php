<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\RecadoException;
use Recado\Sdk\Exception\ValidationException;

/**
 * GET|PATCH /delivery/reputation-limits — the thresholds sender health, the
 * circuit breaker and the transactional alert are judged with.
 */
final class ReputationLimitsTest extends TestCase
{
    public function test_it_reads_the_effective_values_overrides_defaults_and_bounds(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->snapshot()]),
        ], $history);

        $limits = $client->delivery()->reputationLimits();

        $this->assertSame(0.08, $limits->effective('bounce_rate'));
        $this->assertSame(50, $limits->effective('min_sample'));
        $this->assertNull($limits->effective('unknown'));
        $this->assertSame(0.08, $limits->overrides['bounce_rate']);
        $this->assertNull($limits->overrides['min_bounces']);
        $this->assertTrue($limits->isOverridden('bounce_rate'));
        $this->assertFalse($limits->isOverridden('min_bounces'));
        $this->assertSame(0.05, $limits->defaults['bounce_rate']);
        $this->assertSame(5, $limits->defaults['min_bounces']);
        $this->assertSame(['min' => 0, 'max' => 0.1], $limits->limits['bounce_rate']);
        $this->assertSame(['min' => 20, 'max' => 10000], $limits->limits['min_sample']);
        $this->assertSame('project', $limits->source);
        $this->assertTrue($limits->isCustomized());
        $this->assertSame(24, $limits->longWindowHours);
        $this->assertSame(1, $limits->shortWindowHours);

        $this->assertTrue($limits->autoResume->enabled);
        $this->assertNull($limits->autoResume->override);
        $this->assertTrue($limits->autoResume->default);
        $this->assertSame(24, $limits->autoResume->cooldownHours);
        $this->assertSame(7, $limits->autoResume->maxPerDays);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/delivery/reputation-limits', $request->getUri()->getPath());
    }

    public function test_update_is_partial_and_a_null_clears_an_override(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['auto_resume'] = ['enabled' => false, 'override' => false, 'default' => true, 'cooldown_hours' => 24, 'max_per_days' => 7];

        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $snapshot]),
        ], $history);

        $limits = $client->delivery()->updateReputationLimits([
            'bounce_rate' => 0.08,
            'min_bounces' => null,
            'auto_resume_enabled' => false,
        ]);

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/api/v1/delivery/reputation-limits', $request->getUri()->getPath());
        // The null must reach the API: it is what clears the override.
        $this->assertSame(
            '{"bounce_rate":0.08,"min_bounces":null,"auto_resume_enabled":false}',
            (string) $request->getBody(),
        );

        // The answer is the new snapshot.
        $this->assertFalse($limits->autoResume->enabled);
        $this->assertFalse($limits->autoResume->override);
        $this->assertSame(0.08, $limits->effective('bounce_rate'));
    }

    public function test_a_project_on_the_defaults_is_not_customized(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['effective']['bounce_rate'] = 0.05;
        $snapshot['overrides']['bounce_rate'] = null;
        $snapshot['source'] = 'default';

        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $snapshot]),
        ], $history);

        $limits = $client->delivery()->reputationLimits();

        $this->assertFalse($limits->isCustomized());
        $this->assertFalse($limits->isOverridden('bounce_rate'));
    }

    public function test_a_value_outside_its_bounds_is_a_validation_error(): void
    {
        $history = [];
        $message = 'The Bounce rate limit must be greater than 0% and at most 10% (0.1 as a fraction).';
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, ['message' => $message, 'errors' => ['bounce_rate' => [$message]]]),
        ], $history);

        try {
            $client->delivery()->updateReputationLimits(['bounce_rate' => 0.5]);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame([$message], $e->errors()['bounce_rate']);
        }
    }

    public function test_a_sandbox_token_is_refused_on_both_verbs(): void
    {
        $refusal = ['message' => 'Reputation limits are not available in a sandbox.', 'code' => 'not_available_in_sandbox'];

        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, $refusal),
            $this->jsonResponse(404, $refusal),
        ], $history);

        foreach ([
            static fn () => $client->delivery()->reputationLimits(),
            static fn () => $client->delivery()->updateReputationLimits(['min_sample' => 100]),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected a RecadoException.');
            } catch (RecadoException $e) {
                $this->assertTrue($e->isNotAvailableInSandbox());
            }
        }
    }

    /**
     * The documented GET /delivery/reputation-limits example.
     *
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'effective' => ['bounce_rate' => 0.08, 'complaint_rate' => 0.001, 'min_sample' => 50, 'short_min_sample' => 20, 'min_bounces' => 5, 'min_complaints' => 3, 'bounce_warning' => 0.05, 'bounce_critical' => 0.08],
            'overrides' => ['bounce_rate' => 0.08, 'complaint_rate' => null, 'min_sample' => null, 'short_min_sample' => null, 'min_bounces' => null, 'min_complaints' => null, 'bounce_warning' => null, 'bounce_critical' => null],
            'defaults' => ['bounce_rate' => 0.05, 'complaint_rate' => 0.001, 'min_sample' => 50, 'short_min_sample' => 20, 'min_bounces' => 5, 'min_complaints' => 3, 'bounce_warning' => 0.05, 'bounce_critical' => 0.08],
            'limits' => [
                'bounce_rate' => ['min' => 0, 'max' => 0.1],
                'complaint_rate' => ['min' => 0, 'max' => 0.005],
                'min_sample' => ['min' => 20, 'max' => 10000],
                'short_min_sample' => ['min' => 10, 'max' => 5000],
                'min_bounces' => ['min' => 1, 'max' => 1000],
                'min_complaints' => ['min' => 1, 'max' => 100],
                'bounce_warning' => ['min' => 0, 'max' => 0.1],
                'bounce_critical' => ['min' => 0, 'max' => 0.1],
            ],
            'source' => 'project',
            'windows' => ['long_hours' => 24, 'short_hours' => 1],
            'auto_resume' => ['enabled' => true, 'override' => null, 'default' => true, 'cooldown_hours' => 24, 'max_per_days' => 7],
        ];
    }
}
