<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Delivery and engagement statistics for a broadcast, attached to the
 * single-broadcast endpoint (GET /broadcasts/{id}) and to a list requested
 * with `include=stats`.
 *
 * Rates are `null` when their denominator is zero rather than a misleading
 * 0.0, exactly like campaign stats. `delivered` means the channel accepted the
 * notification (for push: the push service), not that it was displayed.
 */
final readonly class BroadcastStats
{
    public function __construct(
        public ?int $queued,
        public ?int $sent,
        public ?int $failed,
        public ?int $delivered,
        public ?int $opened,
        public ?int $clicked,
        public ?float $deliveryRate,
        public ?float $openRate,
        public ?float $clickRate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            queued: isset($data['queued']) ? (int) $data['queued'] : null,
            sent: isset($data['sent']) ? (int) $data['sent'] : null,
            failed: isset($data['failed']) ? (int) $data['failed'] : null,
            delivered: isset($data['delivered']) ? (int) $data['delivered'] : null,
            opened: isset($data['opened']) ? (int) $data['opened'] : null,
            clicked: isset($data['clicked']) ? (int) $data['clicked'] : null,
            deliveryRate: isset($data['delivery_rate']) ? (float) $data['delivery_rate'] : null,
            openRate: isset($data['open_rate']) ? (float) $data['open_rate'] : null,
            clickRate: isset($data['click_rate']) ? (float) $data['click_rate'] : null,
        );
    }
}
