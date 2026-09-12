<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\VerificationEstimate;
use Recado\Sdk\Dto\VerificationRun;
use Recado\Sdk\Exception\ValidationException;
use Recado\Sdk\Exception\VerificationEstimateMismatchException;
use Recado\Sdk\Http\HttpClient;

/**
 * The Verification resource: mailbox-level email verification through the
 * project's OWN ZeroBounce or Kickbox account.
 *
 * This is NOT the free verdict every contact already carries (syntax, MX,
 * disposable providers, typos, role accounts) — that one is computed in-process
 * and always on. These lookups are billed per address to the tenant's account
 * and **there is no refund**, which is why the surface is deliberately two-step
 * and the SDK keeps the cost gate first-class:
 *
 * ```php
 * $estimate = $client->verification()->estimate(listId: 12);
 *
 * if ($estimate->sufficientCredits) {
 *     $run = $client->verification()->run($estimate);
 * }
 * ```
 *
 * `run()` takes the estimate OBJECT, never a bare number: the estimate
 * remembers the scope it was computed for and carries the figure the platform
 * re-checks, so a run can never be started on a different scope or on a number
 * nobody has seen. If the audience moved meanwhile the run is refused with
 * `VerificationEstimateMismatchException`, which hands you the fresh estimate.
 *
 * Other refusals: `422` `verification_not_configured` (no provider, or one
 * switched off — a disabled provider must never spend credits), `409`
 * `verification_already_running` (one run per project at a time), `404`
 * `list_not_found` and `404` `verification_run_not_found`.
 */
final readonly class VerificationResource
{
    public function __construct(private HttpClient $http) {}

    /**
     * What a run would cost, and what the account can afford
     * (GET /verification/estimate).
     *
     * Omit both parameters to scope the estimate to the whole audience.
     * `addresses` is smaller than `contactsInScope` whenever addresses already
     * carry a recent external verdict: those are skipped and cost nothing.
     *
     * @param  array<int, int>  $contactIds  Max 5000.
     */
    public function estimate(?int $listId = null, array $contactIds = []): VerificationEstimate
    {
        $contactIds = array_values(array_map(intval(...), $contactIds));

        $response = $this->http->get('verification/estimate', [
            'query' => $this->scopeQuery($listId, $contactIds),
        ]);

        return VerificationEstimate::fromArray($response['data'] ?? [], $listId, $contactIds);
    }

    /**
     * Start a verification run for the scope the estimate was computed for
     * (POST /verification/runs, `202`).
     *
     * The estimate's `addresses` figure is sent as `confirm_estimate` and
     * re-checked by the platform at start time. A stale number is refused with
     * `VerificationEstimateMismatchException`, carrying the CURRENT estimate —
     * so a client re-confirms a number it has actually seen rather than paying
     * for one it has not. The gate applies in a sandbox too.
     *
     * The returned run has its counters at zero: poll `get()` until
     * `VerificationRun::isFinished()`.
     *
     * @throws VerificationEstimateMismatchException
     */
    public function run(VerificationEstimate $estimate): VerificationRun
    {
        $payload = $estimate->scopePayload();
        $payload['confirm_estimate'] = $estimate->confirmValue();

        try {
            $response = $this->http->post('verification/runs', ['json' => $payload]);
        } catch (ValidationException $exception) {
            if ($exception->getErrorCode() === 'estimate_mismatch') {
                throw VerificationEstimateMismatchException::from(
                    $exception,
                    $estimate->listId,
                    $estimate->contactIds,
                );
            }

            throw $exception;
        }

        return VerificationRun::fromArray($response['data'] ?? []);
    }

    /**
     * The progress of a run this project started (GET /verification/runs/{id}).
     *
     * `updated` counts addresses the provider actually answered for; `failed`
     * counts lookups that could not be made — a provider outage never rewrites
     * a stored verdict.
     */
    public function get(string $id): VerificationRun
    {
        $response = $this->http->get('verification/runs/'.rawurlencode($id));

        return VerificationRun::fromArray($response['data'] ?? []);
    }

    /**
     * The scope parameters both endpoints share.
     *
     * @param  array<int, int>  $contactIds
     * @return array<string, mixed>
     */
    private function scopeQuery(?int $listId, array $contactIds): array
    {
        $query = [];

        if ($listId !== null) {
            $query['list_id'] = $listId;
        }

        if ($contactIds !== []) {
            $query['contact_ids'] = $contactIds;
        }

        return $query;
    }
}
