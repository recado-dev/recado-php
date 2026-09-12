<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The per-channel audience size of a lists/segments selection
 * (GET /broadcasts/recipient-count).
 *
 * Every sendable channel is counted, not only the ones a broadcast selects, so
 * a UI can show what ticking a channel would reach. `recipientsTotal` is the
 * SUM: a contact reachable on two channels receives two notifications, which is
 * exactly how a broadcast's own `recipientsTotal` counts it.
 */
final readonly class BroadcastRecipientCounts
{
    /**
     * @param  array<string, int>  $counts  Keyed by channel (`in_app`, `push`).
     */
    public function __construct(
        public array $counts,
        public int $recipientsTotal,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $counts = [];

        if (is_array($data['counts'] ?? null)) {
            foreach ($data['counts'] as $channel => $count) {
                if (is_numeric($count)) {
                    $counts[(string) $channel] = (int) $count;
                }
            }
        }

        return new self(
            counts: $counts,
            recipientsTotal: (int) ($data['recipients_total'] ?? 0),
        );
    }

    /**
     * The count for one channel, 0 when the channel is absent.
     */
    public function for(string $channel): int
    {
        return $this->counts[$channel] ?? 0;
    }
}
