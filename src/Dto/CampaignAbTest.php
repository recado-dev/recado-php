<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The AUTHORED A/B test of a campaign: the configuration plus the variants
 * as they were written.
 *
 * It is the read counterpart of the `ab_test` + `variants` payload that
 * create() and update() send, and is present on every campaign read and
 * write — unlike the `include=variants` engagement breakdown, which stays
 * opt-in.
 *
 * `locked` is the one field to check before editing: once the test group has
 * gone out (`state` testing, deciding or finished) the variants are frozen
 * and a write touching them fails with `ab_test_locked`.
 */
final readonly class CampaignAbTest
{
    /**
     * @param  array<int, CampaignVariant>  $variants
     */
    public function __construct(
        public bool $enabled,
        public ?string $state,
        public bool $locked,
        public ?float $testFraction,
        public ?string $winnerMetric,
        public ?int $testDurationMinutes,
        public ?string $resolveDueAt,
        public array $variants,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $variants = [];

        foreach ($data['variants'] ?? [] as $variant) {
            if (is_array($variant)) {
                $variants[] = CampaignVariant::fromArray($variant);
            }
        }

        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            state: isset($data['state']) ? (string) $data['state'] : null,
            locked: (bool) ($data['locked'] ?? false),
            testFraction: isset($data['test_fraction']) ? (float) $data['test_fraction'] : null,
            winnerMetric: isset($data['winner_metric']) ? (string) $data['winner_metric'] : null,
            testDurationMinutes: isset($data['test_duration_minutes']) ? (int) $data['test_duration_minutes'] : null,
            resolveDueAt: isset($data['resolve_due_at']) ? (string) $data['resolve_due_at'] : null,
            variants: $variants,
        );
    }
}
