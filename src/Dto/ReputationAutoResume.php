<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The automatic-resume settings of a project's reputation limits
 * (`auto_resume` on GET|PATCH /delivery/reputation-limits).
 *
 * `enabled` is what is in force, `override` the project's own switch (null =
 * it follows `default`, the platform value). `cooldownHours` and `maxPerDays`
 * are the platform-wide timing: a pause caused by bounces only lifts by
 * itself `cooldownHours` after it started, at most once per sending domain
 * every `maxPerDays` days.
 */
final readonly class ReputationAutoResume
{
    public function __construct(
        public bool $enabled,
        public ?bool $override,
        public ?bool $default,
        public ?int $cooldownHours,
        public ?int $maxPerDays,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            override: isset($data['override']) ? (bool) $data['override'] : null,
            default: isset($data['default']) ? (bool) $data['default'] : null,
            cooldownHours: isset($data['cooldown_hours']) ? (int) $data['cooldown_hours'] : null,
            maxPerDays: isset($data['max_per_days']) ? (int) $data['max_per_days'] : null,
        );
    }
}
