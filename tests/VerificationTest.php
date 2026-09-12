<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\VerificationEstimate;
use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\RecadoException;
use Recado\Sdk\Exception\ValidationException;
use Recado\Sdk\Exception\VerificationEstimateMismatchException;

/**
 * External verification: the estimate/confirm cost gate.
 *
 * These lookups are billed per address to the tenant's own provider account
 * with no refund, so the gate is the contract — not a convenience.
 */
final class VerificationTest extends TestCase
{
    public function test_the_estimate_carries_the_scope_it_was_computed_for(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->estimate()]),
        ], $history);

        $estimate = $client->verification()->estimate(listId: 12);

        $this->assertSame('zerobounce', $estimate->provider);
        // Smaller than `contacts_in_scope`: addresses with a recent external
        // verdict are skipped and cost nothing.
        $this->assertSame(1840, $estimate->contactsInScope);
        $this->assertSame(1204, $estimate->addresses);
        $this->assertSame(1204, $estimate->confirmValue());
        $this->assertTrue($estimate->sufficientCredits);
        $this->assertSame(12, $estimate->listId);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/verification/estimate', $request->getUri()->getPath());
        $this->assertSame('list_id=12', $request->getUri()->getQuery());
    }

    public function test_an_omitted_scope_estimates_the_whole_audience(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->estimate()]),
        ], $history);

        $estimate = $client->verification()->estimate();

        $this->assertNull($estimate->listId);
        $this->assertSame([], $estimate->contactIds);
        $this->assertSame('', $history[0]['request']->getUri()->getQuery());
    }

    public function test_run_echoes_the_estimate_figure_and_reuses_its_scope(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->estimate(['addresses' => 3])]),
            $this->jsonResponse(202, ['data' => [
                'id' => '9b1f2c3d-4e5a-6b7c-8d9e-0f1a2b3c4d5e',
                'provider' => 'zerobounce',
                'total' => 3, 'processed' => 0, 'updated' => 0, 'failed' => 0,
                'finished' => false,
                'started_at' => '2026-09-12T10:00:00+00:00',
            ]]),
        ], $history);

        $estimate = $client->verification()->estimate(contactIds: [1, 2, 3]);
        $run = $client->verification()->run($estimate);

        $this->assertSame('9b1f2c3d-4e5a-6b7c-8d9e-0f1a2b3c4d5e', $run->id);
        $this->assertSame(3, $run->total);
        $this->assertFalse($run->isFinished());

        // The run is started on the SAME scope the estimate priced, and the
        // confirmation is the estimate's own figure — never a number the caller
        // made up.
        $this->assertSame(
            ['contact_ids' => [1, 2, 3], 'confirm_estimate' => 3],
            json_decode((string) $history[1]['request']->getBody(), true),
        );
        $this->assertSame('/api/v1/verification/runs', $history[1]['request']->getUri()->getPath());
    }

    public function test_a_stale_figure_is_refused_and_hands_back_the_current_estimate(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->estimate()]),
            $this->jsonResponse(422, [
                'message' => 'The estimate no longer matches.',
                'code' => 'estimate_mismatch',
                'estimate' => $this->estimate(['addresses' => 1290, 'contacts_in_scope' => 1930]),
            ]),
            $this->jsonResponse(202, ['data' => ['id' => 'run-2', 'total' => 1290]]),
        ], $history);

        $estimate = $client->verification()->estimate(listId: 12);

        try {
            $client->verification()->run($estimate);
            $this->fail('Expected a VerificationEstimateMismatchException.');
        } catch (VerificationEstimateMismatchException $e) {
            $this->assertSame('estimate_mismatch', $e->getErrorCode());

            $fresh = $e->currentEstimate();
            $this->assertSame(1290, $fresh->addresses);
            // Re-tagged with the scope the refused run asked for, so it can go
            // straight back into run().
            $this->assertSame(12, $fresh->listId);

            $run = $client->verification()->run($fresh);
            $this->assertSame('run-2', $run->id);
        }

        $this->assertSame(
            ['list_id' => 12, 'confirm_estimate' => 1290],
            json_decode((string) $history[2]['request']->getBody(), true),
        );
    }

    /**
     * The mismatch type extends ValidationException, so code that only catches
     * that keeps working.
     */
    public function test_the_mismatch_is_still_a_validation_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The estimate no longer matches.',
                'code' => 'estimate_mismatch',
            ]),
        ], $history);

        try {
            $client->verification()->run($this->estimateDto());
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertInstanceOf(VerificationEstimateMismatchException::class, $e);
            // No `estimate` block on the body: nothing to re-confirm with.
            $this->assertNull($e->currentEstimate());
        }
    }

    public function test_other_refusals_keep_their_own_codes(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'No verification provider is configured.',
                'code' => 'verification_not_configured',
            ]),
            $this->jsonResponse(409, [
                'message' => 'A verification run is already in progress.',
                'code' => 'verification_already_running',
            ]),
            $this->jsonResponse(404, [
                'message' => 'Verification run not found.',
                'code' => 'verification_run_not_found',
            ]),
        ], $history);

        try {
            $client->verification()->run($this->estimateDto());
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            // A disabled provider must never spend credits.
            $this->assertSame('verification_not_configured', $e->getErrorCode());
            $this->assertNotInstanceOf(VerificationEstimateMismatchException::class, $e);
        }

        try {
            $client->verification()->run($this->estimateDto());
            $this->fail('Expected a RecadoException.');
        } catch (RecadoException $e) {
            // One run per project at a time — a 409, not a 422.
            $this->assertSame('verification_already_running', $e->getErrorCode());
            $this->assertSame(409, $e->getStatus());
        }

        try {
            $client->verification()->get('unknown-run');
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('verification_run_not_found', $e->getErrorCode());
        }
    }

    public function test_polling_a_run_reports_the_advancing_counters(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'id' => 'run-1', 'provider' => 'kickbox',
                'total' => 1204, 'processed' => 1204, 'updated' => 1180, 'failed' => 24,
                'finished' => true, 'started_at' => '2026-09-12T10:00:00+00:00',
            ]]),
        ], $history);

        $run = $client->verification()->get('run-1');

        $this->assertTrue($run->isFinished());
        // A provider outage never rewrites a stored verdict: those lookups are
        // counted as failed, not as updates.
        $this->assertSame(1180, $run->updated);
        $this->assertSame(24, $run->failed);
        $this->assertSame('/api/v1/verification/runs/run-1', $history[0]['request']->getUri()->getPath());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function estimate(array $overrides = []): array
    {
        return array_merge([
            'provider' => 'zerobounce',
            'contacts_in_scope' => 1840,
            'addresses' => 1204,
            'estimated_credits' => 1204,
            'credits_remaining' => 5000,
            'sufficient_credits' => true,
            'running' => false,
        ], $overrides);
    }

    private function estimateDto(): VerificationEstimate
    {
        return VerificationEstimate::fromArray($this->estimate(), 12);
    }
}
