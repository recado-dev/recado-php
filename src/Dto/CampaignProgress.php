<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Where a campaign's audience stands (GET /campaigns/{id} and the
 * create/update responses; never on the listing).
 *
 * `pending` is the recipients still waiting to go out: queued messages plus,
 * while dispatch is unfinished, recipients with no message yet (a warm-up or
 * breaker-parked campaign included). `skipped` is the recipients that got no
 * message once dispatch FINISHED (frequency cap, suppressed or unsubscribed
 * meanwhile...), and null before — a missing message is not a skip yet.
 */
final readonly class CampaignProgress
{
    public function __construct(
        public int $pending,
        public ?int $skipped,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pending: (int) ($data['pending'] ?? 0),
            skipped: isset($data['skipped']) ? (int) $data['skipped'] : null,
        );
    }
}
