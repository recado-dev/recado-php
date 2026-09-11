<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * An A/B variant, in both of the shapes the API returns it in.
 *
 * The AUTHORED fields (subject, preheader, sender, content) come back on the
 * `ab_test` block of every campaign read and write: a null one means the
 * variant inherits that campaign field, which is exactly the distinction a
 * client editing the draft needs. The ENGAGEMENT fields (sent, delivered,
 * opens, clicks, rates) come back on `include=variants` and are null
 * otherwise. The winner's numbers include the remainder send.
 */
final readonly class CampaignVariant
{
    /**
     * @param  array<string, mixed>|null  $content  The authored body in the
     *                                              campaign's editor shape.
     */
    public function __construct(
        public ?int $id,
        public ?string $label,
        public ?string $subject,
        public ?bool $isWinner,
        public ?int $sent = null,
        public ?int $delivered = null,
        public ?int $uniqueOpens = null,
        public ?int $uniqueClicks = null,
        public ?float $openRate = null,
        public ?float $clickRate = null,
        public ?string $preheader = null,
        public ?string $fromName = null,
        public ?string $fromEmail = null,
        public ?array $content = null,
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
            preheader: isset($data['preheader']) ? (string) $data['preheader'] : null,
            fromName: isset($data['from_name']) ? (string) $data['from_name'] : null,
            fromEmail: isset($data['from_email']) ? (string) $data['from_email'] : null,
            content: is_array($data['content'] ?? null) ? $data['content'] : null,
        );
    }
}
