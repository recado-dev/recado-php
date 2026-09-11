<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A campaign (a newsletter / broadcast).
 *
 * `stats` is populated by the single-campaign endpoint (GET /campaigns/{id})
 * and by a list requested with `include=stats`; it is null otherwise.
 * `topLinks` and `variants` are null unless the detail call asked for them
 * (`include=top_links,variants`) — an A/B-less campaign asked for `variants`
 * gets an empty array, which is how "requested but empty" stays distinct from
 * "not requested".
 *
 * `abTest` is the AUTHORED test (configuration + the variants as written) and
 * needs no include: the detail, create and update endpoints all return it.
 * It is null on list rows, which never carry it.
 */
final readonly class Campaign
{
    /**
     * @param  array<int, CampaignTopLink>|null  $topLinks
     * @param  array<int, CampaignVariant>|null  $variants
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $subject,
        public ?string $status,
        public ?int $recipientsTotal,
        public ?int $dispatchedTotal,
        public ?int $sentCount,
        public ?int $failedCount,
        public ?string $scheduledAt,
        public ?string $startedAt,
        public ?string $finishedAt,
        public ?string $createdAt,
        public ?CampaignStats $stats,
        public ?array $topLinks = null,
        public ?array $variants = null,
        public ?CampaignAbTest $abTest = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $stats = isset($data['stats']) && is_array($data['stats'])
            ? CampaignStats::fromArray($data['stats'])
            : null;

        $topLinks = null;

        if (is_array($data['top_links'] ?? null)) {
            $topLinks = [];

            foreach ($data['top_links'] as $link) {
                if (is_array($link)) {
                    $topLinks[] = CampaignTopLink::fromArray($link);
                }
            }
        }

        $variants = null;

        if (is_array($data['variants'] ?? null)) {
            $variants = [];

            foreach ($data['variants'] as $variant) {
                if (is_array($variant)) {
                    $variants[] = CampaignVariant::fromArray($variant);
                }
            }
        }

        $abTest = is_array($data['ab_test'] ?? null)
            ? CampaignAbTest::fromArray($data['ab_test'])
            : null;

        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            recipientsTotal: isset($data['recipients_total']) ? (int) $data['recipients_total'] : null,
            dispatchedTotal: isset($data['dispatched_total']) ? (int) $data['dispatched_total'] : null,
            sentCount: isset($data['sent_count']) ? (int) $data['sent_count'] : null,
            failedCount: isset($data['failed_count']) ? (int) $data['failed_count'] : null,
            scheduledAt: isset($data['scheduled_at']) ? (string) $data['scheduled_at'] : null,
            startedAt: isset($data['started_at']) ? (string) $data['started_at'] : null,
            finishedAt: isset($data['finished_at']) ? (string) $data['finished_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            stats: $stats,
            topLinks: $topLinks,
            variants: $variants,
            abTest: $abTest,
        );
    }
}
