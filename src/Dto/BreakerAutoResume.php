<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * What will happen to a circuit-breaker pause: does it lift by itself?
 *
 * `eligible` true means the pause resumes automatically at `at` (24 hours
 * after the trip). Otherwise it stays until a person resumes it in the
 * dashboard, `at` is null and `reason` says why:
 *
 * - `complaints` — spam complaints took part in the trip; such a pause is
 *   never resumed automatically.
 * - `auto_resume_used` — the identity was already resumed automatically in
 *   the 7 days before this trip (one automatic resume per identity per 7 days).
 * - `disabled` — automatic resume is turned off for the project (see
 *   `delivery()->reputationLimits()`).
 * - `unknown_cause` — the trip was recorded before automatic resume existed.
 *
 * The same block appears on `SendingDomainHealth::autoResume()` (while
 * paused), on a `trip` entry of the breaker history (as judged when it
 * tripped), on `SendingDomain::autoResume()` and on the
 * `identity.breaker_tripped` webhook.
 */
final readonly class BreakerAutoResume
{
    public function __construct(
        public bool $eligible,
        public ?string $at,
        public ?string $reason,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eligible: (bool) ($data['eligible'] ?? false),
            at: isset($data['at']) && is_string($data['at']) ? $data['at'] : null,
            reason: isset($data['reason']) && is_string($data['reason']) ? $data['reason'] : null,
        );
    }

    /**
     * Whether the pause needs a person: it will not lift by itself.
     */
    public function needsManualResume(): bool
    {
        return ! $this->eligible;
    }
}
