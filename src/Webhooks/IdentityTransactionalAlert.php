<?php

declare(strict_types=1);

namespace Recado\Sdk\Webhooks;

/**
 * The `data` of an `identity.transactional_alert` webhook: the TRANSACTIONAL
 * (non-marketing) email of one of the project's sending domains hard-bounces
 * or draws complaints above an alert level over `windowHours`.
 *
 * It is a warning, never an action: nothing is paused and transactional email
 * keeps sending (`paused` is always false).
 *
 * - `level` is `warning` (hard-bounce rate above `bounceWarningThreshold`, or
 *   complaint rate above `complaintThreshold`) or `critical` (hard-bounce rate
 *   above `bounceCriticalThreshold`). `metric` says which rate raised it:
 *   `bounce`, `complaint` or `both`.
 * - `complaints` counts unique recipients, `bounces` hard bounces.
 * - At most one alert per domain every 24 hours, plus one more when a
 *   `warning` escalates to `critical` inside that period.
 *
 * The payload never contains recipient addresses, and sandboxes never emit it.
 */
final readonly class IdentityTransactionalAlert
{
    /**
     * @param  array<string, mixed>  $raw  The raw `data` block.
     */
    public function __construct(
        public ?string $domain,
        public ?string $level,
        public ?string $traffic,
        public ?string $metric,
        public bool $paused,
        public ?float $bounceRate,
        public ?float $complaintRate,
        public ?int $sample,
        public ?int $bounces,
        public ?int $complaints,
        public ?float $bounceWarningThreshold,
        public ?float $bounceCriticalThreshold,
        public ?float $complaintThreshold,
        public ?int $windowHours,
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
            level: isset($data['level']) ? (string) $data['level'] : null,
            traffic: isset($data['traffic']) ? (string) $data['traffic'] : null,
            metric: isset($data['metric']) ? (string) $data['metric'] : null,
            paused: (bool) ($data['paused'] ?? false),
            bounceRate: isset($rates['bounce_rate']) ? (float) $rates['bounce_rate'] : null,
            complaintRate: isset($rates['complaint_rate']) ? (float) $rates['complaint_rate'] : null,
            sample: isset($data['sample']) ? (int) $data['sample'] : null,
            bounces: isset($data['bounces']) ? (int) $data['bounces'] : null,
            complaints: isset($data['complaints']) ? (int) $data['complaints'] : null,
            bounceWarningThreshold: isset($thresholds['bounce_warning']) ? (float) $thresholds['bounce_warning'] : null,
            bounceCriticalThreshold: isset($thresholds['bounce_critical']) ? (float) $thresholds['bounce_critical'] : null,
            complaintThreshold: isset($thresholds['complaint_rate']) ? (float) $thresholds['complaint_rate'] : null,
            windowHours: isset($data['window_hours']) ? (int) $data['window_hours'] : null,
            raw: $data,
        );
    }

    public function isCritical(): bool
    {
        return $this->level === 'critical';
    }
}
