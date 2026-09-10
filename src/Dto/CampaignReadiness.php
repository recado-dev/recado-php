<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The verdict of GET /campaigns/{id}/readiness: the same pre-flight a send
 * runs, without starting anything.
 *
 * `ready` means POST /campaigns/{id}/send will not fail on any of these
 * grounds; `schedulable` is the same verdict for the schedule endpoint (the
 * `audience` and `quota` checks do not gate scheduling).
 */
final readonly class CampaignReadiness
{
    /**
     * @param  array<int, CampaignReadinessCheck>  $checks
     */
    public function __construct(
        public bool $ready,
        public bool $schedulable,
        public ?int $recipientsTotal,
        public array $checks,
    ) {}

    /**
     * The failing checks, in the order the API returned them.
     *
     * @return array<int, CampaignReadinessCheck>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->checks, fn (CampaignReadinessCheck $check): bool => ! $check->passed));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $checks = [];

        if (is_array($data['checks'] ?? null)) {
            foreach ($data['checks'] as $check) {
                if (is_array($check)) {
                    $checks[] = CampaignReadinessCheck::fromArray($check);
                }
            }
        }

        return new self(
            ready: (bool) ($data['ready'] ?? false),
            schedulable: (bool) ($data['schedulable'] ?? false),
            recipientsTotal: isset($data['recipients_total']) ? (int) $data['recipients_total'] : null,
            checks: $checks,
        );
    }
}
