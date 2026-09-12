<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The signup breakdown of one waiting list.
 *
 * These are TOTAL member rows, not active subscribers: they measure interest,
 * and a later unsubscribe does not retroactively un-sign-up anyone.
 * `referredShare` is a percentage (percentage points, not a 0..1 ratio).
 */
final readonly class WaitlistStats
{
    public function __construct(
        public int $total,
        public int $last7Days,
        public int $referred,
        public float $referredShare,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            total: (int) ($data['total'] ?? 0),
            last7Days: (int) ($data['last_7_days'] ?? 0),
            referred: (int) ($data['referred'] ?? 0),
            referredShare: (float) ($data['referred_share'] ?? 0),
        );
    }
}
