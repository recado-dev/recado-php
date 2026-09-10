<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * An A/B variant with its own engagement numbers, returned by
 * GET /campaigns/{id}?include=variants.
 *
 * Reporting only: variants are authored in the dashboard, never through the
 * API. The winner's numbers include the remainder send.
 */
final readonly class CampaignVariant
{
    public function __construct(
        public ?int $id,
        public ?string $label,
        public ?string $subject,
        public ?bool $isWinner,
        public ?int $sent,
        public ?int $delivered,
        public ?int $uniqueOpens,
        public ?int $uniqueClicks,
        public ?float $openRate,
        public ?float $clickRate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            label: isset($data['label']) ? (string) $data['label'] : null,
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            isWinner: isset($data['is_winner']) ? (bool) $data['is_winner'] : null,
            sent: isset($data['sent']) ? (int) $data['sent'] : null,
            delivered: isset($data['delivered']) ? (int) $data['delivered'] : null,
            uniqueOpens: isset($data['unique_opens']) ? (int) $data['unique_opens'] : null,
            uniqueClicks: isset($data['unique_clicks']) ? (int) $data['unique_clicks'] : null,
            openRate: isset($data['open_rate']) ? (float) $data['open_rate'] : null,
            clickRate: isset($data['click_rate']) ? (float) $data['click_rate'] : null,
        );
    }
}
