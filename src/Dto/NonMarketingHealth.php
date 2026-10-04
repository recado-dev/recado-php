<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The TRANSACTIONAL (non-marketing) traffic of one sending identity over the
 * health window (`non_marketing` on GET /delivery/health).
 *
 * It never moves the identity's `health` and can never pause anything:
 * transactional email is never blocked, held or deferred. What it carries is
 * an alert-only verdict, because the provider judges the whole account:
 * `alertLevel` is null (below every threshold, or not judged yet), `warning`
 * or `critical`, judged against `alertThresholds`
 * (`{bounce_warning, bounce_critical, complaint}`, the project's resolved
 * values). `alertedAt` is the time of the last alert sent to the team owner
 * while it still suppresses a new one, null otherwise.
 *
 * `complaints` counts unique recipients, `bounces` hard bounces per message.
 */
final readonly class NonMarketingHealth
{
    /**
     * @param  array<string, float>  $alertThresholds
     */
    public function __construct(
        public ?int $sample,
        public ?int $bounces,
        public ?int $complaints,
        public ?float $bounceRate,
        public ?float $complaintRate,
        public ?string $alertLevel,
        public array $alertThresholds,
        public ?string $alertedAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $thresholds = [];

        foreach (is_array($data['alert_thresholds'] ?? null) ? $data['alert_thresholds'] : [] as $key => $value) {
            if (is_string($key) && is_numeric($value)) {
                $thresholds[$key] = (float) $value;
            }
        }

        return new self(
            sample: isset($data['sample']) ? (int) $data['sample'] : null,
            bounces: isset($data['bounces']) ? (int) $data['bounces'] : null,
            complaints: isset($data['complaints']) ? (int) $data['complaints'] : null,
            bounceRate: isset($data['bounce_rate']) ? (float) $data['bounce_rate'] : null,
            complaintRate: isset($data['complaint_rate']) ? (float) $data['complaint_rate'] : null,
            alertLevel: isset($data['alert_level']) ? (string) $data['alert_level'] : null,
            alertThresholds: $thresholds,
            alertedAt: isset($data['alerted_at']) ? (string) $data['alerted_at'] : null,
        );
    }

    /**
     * Whether the transactional rates are above an alert threshold right now
     * (`warning` or `critical`). Nothing is paused either way.
     */
    public function isAlerting(): bool
    {
        return $this->alertLevel !== null;
    }

    public function isCritical(): bool
    {
        return $this->alertLevel === 'critical';
    }
}
