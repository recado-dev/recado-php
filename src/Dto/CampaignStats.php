<?php

declare(strict_types=1);

namespace Mailer\Sdk\Dto;

/**
 * Engagement and deliverability statistics for a campaign, attached to the
 * single-campaign endpoint (GET /campaigns/{id}).
 *
 * Rates are `null` when their denominator is zero (e.g. no delivery feedback
 * configured) rather than a misleading 0.0.
 */
final readonly class CampaignStats
{
    public function __construct(
        public ?int $delivered,
        public ?int $uniqueOpens,
        public ?int $totalOpens,
        public ?int $uniqueClicks,
        public ?int $totalClicks,
        public ?int $bounced,
        public ?int $complained,
        public ?int $unsubscribed,
        public ?float $openRate,
        public ?float $clickRate,
        public ?float $clickToOpenRate,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            delivered: isset($data['delivered']) ? (int) $data['delivered'] : null,
            uniqueOpens: isset($data['unique_opens']) ? (int) $data['unique_opens'] : null,
            totalOpens: isset($data['total_opens']) ? (int) $data['total_opens'] : null,
            uniqueClicks: isset($data['unique_clicks']) ? (int) $data['unique_clicks'] : null,
            totalClicks: isset($data['total_clicks']) ? (int) $data['total_clicks'] : null,
            bounced: isset($data['bounced']) ? (int) $data['bounced'] : null,
            complained: isset($data['complained']) ? (int) $data['complained'] : null,
            unsubscribed: isset($data['unsubscribed']) ? (int) $data['unsubscribed'] : null,
            openRate: isset($data['open_rate']) ? (float) $data['open_rate'] : null,
            clickRate: isset($data['click_rate']) ? (float) $data['click_rate'] : null,
            clickToOpenRate: isset($data['click_to_open_rate']) ? (float) $data['click_to_open_rate'] : null,
        );
    }
}
