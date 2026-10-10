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
 * `unreached` is the recipients dispatch never got to because it was CUT
 * SHORT (the campaign failed or was cancelled before dispatch finished): not
 * a skip, since nobody decided not to email them. 0 in every other case, and
 * for an API that predates the key.
 *
 * Every recipient is in one place only, so `messages + (pending - queued) +
 * (skipped ?? 0) + unreached = recipientsTotal`.
 */
final readonly class CampaignProgress
{
    public function __construct(
        public int $pending,
        public ?int $skipped,
        public int $unreached = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pending: (int) ($data['pending'] ?? 0),
            skipped: isset($data['skipped']) ? (int) $data['skipped'] : null,
            unreached: (int) ($data['unreached'] ?? 0),
        );
    }
}
