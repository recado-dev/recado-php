<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The thresholds a project's sending reputation is judged with
 * (GET|PATCH /delivery/reputation-limits).
 *
 * Every map is keyed by the limit name — `bounce_rate`, `complaint_rate`,
 * `min_sample`, `short_min_sample`, `min_bounces`, `min_complaints` (the
 * marketing circuit breaker's) and `bounce_warning`, `bounce_critical` (the
 * transactional alert's; they only alert, transactional email is never
 * paused). Rates are FRACTIONS (`0.05` = 5%).
 *
 * - `effective` — the values in force (what sender health, the breaker and
 *   the alert use).
 * - `overrides` — the project's own values, null where it inherits the default.
 * - `defaults` — the platform values.
 * - `limits` — the bounds an override must respect, `{min, max}` per key. For
 *   a rate `min` is exclusive and `max` inclusive; for a count both are
 *   inclusive.
 * - `source` — `project` as soon as one limit is overridden, else `default`.
 * - `longWindowHours` / `shortWindowHours` — the breaker's two windows
 *   (platform-wide, not configurable).
 * - `autoResume` — the automatic resume of a breaker pause.
 *
 * The limits apply to every sending domain of the project; there is no
 * per-domain override.
 */
final readonly class ReputationLimits
{
    /**
     * @param  array<string, float|int>  $effective
     * @param  array<string, float|int|null>  $overrides
     * @param  array<string, float|int>  $defaults
     * @param  array<string, array{min: float|int|null, max: float|int|null}>  $limits
     */
    public function __construct(
        public array $effective,
        public array $overrides,
        public array $defaults,
        public array $limits,
        public ?string $source,
        public ?int $longWindowHours,
        public ?int $shortWindowHours,
        public ReputationAutoResume $autoResume,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $windows = is_array($data['windows'] ?? null) ? $data['windows'] : [];

        $overrides = [];
        foreach (is_array($data['overrides'] ?? null) ? $data['overrides'] : [] as $key => $value) {
            if (is_string($key)) {
                $overrides[$key] = is_numeric($value) ? self::number($value) : null;
            }
        }

        $limits = [];
        foreach (is_array($data['limits'] ?? null) ? $data['limits'] : [] as $key => $bounds) {
            if (is_string($key) && is_array($bounds)) {
                $limits[$key] = [
                    'min' => is_numeric($bounds['min'] ?? null) ? self::number($bounds['min']) : null,
                    'max' => is_numeric($bounds['max'] ?? null) ? self::number($bounds['max']) : null,
                ];
            }
        }

        return new self(
            effective: self::values($data['effective'] ?? null),
            overrides: $overrides,
            defaults: self::values($data['defaults'] ?? null),
            limits: $limits,
            source: isset($data['source']) ? (string) $data['source'] : null,
            longWindowHours: isset($windows['long_hours']) ? (int) $windows['long_hours'] : null,
            shortWindowHours: isset($windows['short_hours']) ? (int) $windows['short_hours'] : null,
            autoResume: ReputationAutoResume::fromArray(
                is_array($data['auto_resume'] ?? null) ? $data['auto_resume'] : [],
            ),
        );
    }

    /**
     * The value in force for one limit, or null for an unknown key.
     */
    public function effective(string $key): float|int|null
    {
        return $this->effective[$key] ?? null;
    }

    /**
     * Whether the project overrides this limit (false = it follows the
     * platform default).
     */
    public function isOverridden(string $key): bool
    {
        return ($this->overrides[$key] ?? null) !== null;
    }

    /**
     * Whether the project overrides at least one limit.
     */
    public function isCustomized(): bool
    {
        return $this->source === 'project';
    }

    /**
     * @return array<string, float|int>
     */
    private static function values(mixed $map): array
    {
        $values = [];

        foreach (is_array($map) ? $map : [] as $key => $value) {
            if (is_string($key) && is_numeric($value)) {
                $values[$key] = self::number($value);
            }
        }

        return $values;
    }

    private static function number(mixed $value): float|int
    {
        return is_int($value) ? $value : (float) $value;
    }
}
