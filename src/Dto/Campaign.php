<?php

declare(strict_types=1);

namespace Mailer\Sdk\Dto;

/**
 * A campaign (a newsletter / broadcast). `stats` is only populated on the
 * single-campaign endpoint (GET /campaigns/{id}); it is null on list items.
 */
final readonly class Campaign
{
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
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $stats = isset($data['stats']) && is_array($data['stats'])
            ? CampaignStats::fromArray($data['stats'])
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
        );
    }
}
