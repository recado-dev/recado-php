<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The 30-day totals of one notification channel (GET /notifications/analytics).
 *
 * `deliveryRate` is over `sent`; `openRate` and `clickRate` are over
 * `delivered`. Every rate is null on a zero denominator, never a fake 0.0.
 *
 * Honest scope note: `delivered` means the channel ACCEPTED the notification
 * (for push, the push service did). On-screen display confirmation is out of
 * scope.
 */
final readonly class NotificationChannelStats
{
    /**
     * @param  array<string, int>  $bySource  Keyed by `api`, `automation`, `broadcast`.
     */
    public function __construct(
        public int $total,
        public int $queued,
        public int $sent,
        public int $failed,
        public array $bySource,
        public int $delivered,
        public int $opened,
        public int $clicked,
        public ?float $deliveryRate,
        public ?float $openRate,
        public ?float $clickRate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $bySource = [];

        if (is_array($data['by_source'] ?? null)) {
            foreach ($data['by_source'] as $source => $count) {
                if (is_numeric($count)) {
                    $bySource[(string) $source] = (int) $count;
                }
            }
        }

        return new self(
            total: (int) ($data['total'] ?? 0),
            queued: (int) ($data['queued'] ?? 0),
            sent: (int) ($data['sent'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            bySource: $bySource,
            delivered: (int) ($data['delivered'] ?? 0),
            opened: (int) ($data['opened'] ?? 0),
            clicked: (int) ($data['clicked'] ?? 0),
            deliveryRate: isset($data['delivery_rate']) ? (float) $data['delivery_rate'] : null,
            openRate: isset($data['open_rate']) ? (float) $data['open_rate'] : null,
            clickRate: isset($data['click_rate']) ? (float) $data['click_rate'] : null,
        );
    }
}
