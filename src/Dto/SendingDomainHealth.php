<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The health block of one VERIFIED sending identity (GET /delivery/health).
 *
 * The rates are the rolling bounce/complaint rates the reputation circuit
 * breaker acts on over `windowHours`, and `health` is the traffic light:
 * `healthy`, `warning` (one rate reached `warningRatio` × its threshold),
 * `paused` (the breaker tripped this identity — campaign sending is held) or
 * `insufficient_data` (fewer than `minSample` sends in the window; the rates
 * are reported but never judged).
 *
 * Complaints count per unique RECIPIENT (`complaints`), bounces per message
 * (`bounces`). The complaint rate is only judged once `minComplaints` unique
 * complainers exist in the window (`complaintJudged`); the `*OverLimit` flags
 * apply the breaker's own rule, and `overLimit` covers both of its windows
 * (`overLimitWindowHours` names the one that is over). An identity that is
 * over the limit but not paused yet reports `health` `warning` and is paused
 * at the breaker's next evaluation. These keys are null against an older API.
 *
 * `warmup` is null for an identity that is not warm-up limited (completed,
 * skipped, or never tracked); `breaker` is null unless the identity is paused.
 * Resuming a paused identity and skipping warm-up stay HUMAN actions in the
 * dashboard — there is no API for them.
 */
final readonly class SendingDomainHealth
{
    /**
     * @param  array<string, mixed>|null  $warmup  `{status, day_number}`.
     * @param  array<string, mixed>|null  $breaker  `{tripped_at, reason}`.
     */
    public function __construct(
        public ?int $id,
        public ?string $domain,
        public ?string $health,
        public ?int $sample,
        public ?int $minSample,
        public ?int $windowHours,
        public ?float $bounceRate,
        public ?float $complaintRate,
        public ?float $bounceThreshold,
        public ?float $complaintThreshold,
        public ?float $warningRatio,
        public ?array $warmup,
        public ?array $breaker,
        public ?int $bounces = null,
        public ?int $complaints = null,
        public ?int $minComplaints = null,
        public ?bool $bounceJudged = null,
        public ?bool $complaintJudged = null,
        public ?bool $bounceOverLimit = null,
        public ?bool $complaintOverLimit = null,
        public ?bool $overLimit = null,
        public ?int $overLimitWindowHours = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            domain: isset($data['domain']) ? (string) $data['domain'] : null,
            health: isset($data['health']) ? (string) $data['health'] : null,
            sample: isset($data['sample']) ? (int) $data['sample'] : null,
            minSample: isset($data['min_sample']) ? (int) $data['min_sample'] : null,
            windowHours: isset($data['window_hours']) ? (int) $data['window_hours'] : null,
            bounceRate: isset($data['bounce_rate']) ? (float) $data['bounce_rate'] : null,
            complaintRate: isset($data['complaint_rate']) ? (float) $data['complaint_rate'] : null,
            bounceThreshold: isset($data['bounce_threshold']) ? (float) $data['bounce_threshold'] : null,
            complaintThreshold: isset($data['complaint_threshold']) ? (float) $data['complaint_threshold'] : null,
            warningRatio: isset($data['warning_ratio']) ? (float) $data['warning_ratio'] : null,
            warmup: is_array($data['warmup'] ?? null) ? $data['warmup'] : null,
            breaker: is_array($data['breaker'] ?? null) ? $data['breaker'] : null,
            bounces: isset($data['bounces']) ? (int) $data['bounces'] : null,
            complaints: isset($data['complaints']) ? (int) $data['complaints'] : null,
            minComplaints: isset($data['min_complaints']) ? (int) $data['min_complaints'] : null,
            bounceJudged: isset($data['bounce_judged']) ? (bool) $data['bounce_judged'] : null,
            complaintJudged: isset($data['complaint_judged']) ? (bool) $data['complaint_judged'] : null,
            bounceOverLimit: isset($data['bounce_over_limit']) ? (bool) $data['bounce_over_limit'] : null,
            complaintOverLimit: isset($data['complaint_over_limit']) ? (bool) $data['complaint_over_limit'] : null,
            overLimit: isset($data['over_limit']) ? (bool) $data['over_limit'] : null,
            overLimitWindowHours: isset($data['over_limit_window_hours'])
                ? (int) $data['over_limit_window_hours']
                : null,
        );
    }

    /**
     * Whether the circuit breaker is currently holding this identity.
     */
    public function isPaused(): bool
    {
        return $this->health === 'paused';
    }

    /**
     * Whether the identity is over a breaker limit (either window): it is
     * paused already or will be at the breaker's next evaluation.
     */
    public function isOverLimit(): bool
    {
        return $this->overLimit === true;
    }
}
