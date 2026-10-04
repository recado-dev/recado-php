<?php

declare(strict_types=1);

namespace Recado\Sdk\Webhooks;

use Recado\Sdk\Dto\BreakerAutoResume;

/**
 * The `data` of an `identity.breaker_tripped` webhook: the reputation circuit
 * breaker paused MARKETING email through one of the project's sending domains
 * (campaigns defer, marketing automation emails are held; transactional email
 * keeps sending).
 *
 * The rates and thresholds are fractions over `windowHours`. `autoResume`
 * says what will happen to the pause: `eligible` true means it lifts by itself
 * at `at`; otherwise it needs a manual resume in the dashboard and `reason`
 * says why. It is null from a server that predates automatic resume.
 */
final readonly class IdentityBreakerTripped
{
    /**
     * @param  array<string, mixed>  $raw  The raw `data` block.
     */
    public function __construct(
        public ?string $domain,
        public ?float $bounceRate,
        public ?float $complaintRate,
        public ?int $sample,
        public ?float $bounceThreshold,
        public ?float $complaintThreshold,
        public ?int $windowHours,
        public ?BreakerAutoResume $autoResume,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The webhook `data` block.
     */
    public static function fromArray(array $data): self
    {
        $rates = is_array($data['rates'] ?? null) ? $data['rates'] : [];
        $thresholds = is_array($data['thresholds'] ?? null) ? $data['thresholds'] : [];

        return new self(
            domain: isset($data['domain']) ? (string) $data['domain'] : null,
            bounceRate: isset($rates['bounce_rate']) ? (float) $rates['bounce_rate'] : null,
            complaintRate: isset($rates['complaint_rate']) ? (float) $rates['complaint_rate'] : null,
            sample: isset($data['sample']) ? (int) $data['sample'] : null,
            bounceThreshold: isset($thresholds['bounce_rate']) ? (float) $thresholds['bounce_rate'] : null,
            complaintThreshold: isset($thresholds['complaint_rate']) ? (float) $thresholds['complaint_rate'] : null,
            windowHours: isset($data['window_hours']) ? (int) $data['window_hours'] : null,
            autoResume: is_array($data['auto_resume'] ?? null)
                ? BreakerAutoResume::fromArray($data['auto_resume'])
                : null,
            raw: $data,
        );
    }

    /**
     * Whether the pause will lift by itself (after its cooldown).
     */
    public function willResumeAutomatically(): bool
    {
        return $this->autoResume?->eligible === true;
    }
}
