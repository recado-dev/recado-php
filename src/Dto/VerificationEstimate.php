<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * What an external verification run would cost (GET `/verification/estimate`).
 *
 * These lookups are billed per address to the TENANT's own ZeroBounce/Kickbox
 * account and there is no refund, which is why the estimate is a first-class
 * object rather than a number: it remembers the SCOPE it was computed for
 * (`listId` / `contactIds`) and carries the figure the run has to echo back, so
 * `verification()->run($estimate)` can never start a run on a different scope
 * or a number nobody saw.
 *
 * `addresses` is smaller than `contactsInScope` whenever addresses already
 * carry an external verdict newer than the re-verify window (30 days by
 * default): those are skipped and cost nothing, which is what makes re-running
 * the same list cheap.
 */
final readonly class VerificationEstimate
{
    /**
     * @param  array<int, int>  $contactIds  The scope this estimate was computed for.
     */
    public function __construct(
        public ?string $provider,
        public int $contactsInScope,
        public int $addresses,
        public int $estimatedCredits,
        public ?int $creditsRemaining,
        public bool $sufficientCredits,
        public bool $running,
        public ?int $listId = null,
        public array $contactIds = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $contactIds
     */
    public static function fromArray(array $data, ?int $listId = null, array $contactIds = []): self
    {
        return new self(
            provider: isset($data['provider']) ? (string) $data['provider'] : null,
            contactsInScope: (int) ($data['contacts_in_scope'] ?? 0),
            addresses: (int) ($data['addresses'] ?? 0),
            estimatedCredits: (int) ($data['estimated_credits'] ?? 0),
            // null means the provider does not report a balance, which is not
            // the same as "no credits left".
            creditsRemaining: isset($data['credits_remaining']) ? (int) $data['credits_remaining'] : null,
            sufficientCredits: (bool) ($data['sufficient_credits'] ?? false),
            running: (bool) ($data['running'] ?? false),
            listId: $listId,
            contactIds: $contactIds,
        );
    }

    /**
     * The figure `POST /verification/runs` expects in `confirm_estimate`. It
     * must still match at the moment the run starts — the platform re-computes
     * it and refuses a stale number with `estimate_mismatch`.
     */
    public function confirmValue(): int
    {
        return $this->addresses;
    }

    /**
     * The scope parameters this estimate was computed for, ready to be sent
     * back with the run.
     *
     * @return array<string, mixed>
     */
    public function scopePayload(): array
    {
        $payload = [];

        if ($this->listId !== null) {
            $payload['list_id'] = $this->listId;
        }

        if ($this->contactIds !== []) {
            $payload['contact_ids'] = $this->contactIds;
        }

        return $payload;
    }
}
