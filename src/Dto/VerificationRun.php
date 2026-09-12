<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * An external verification run (POST `/verification/runs`, GET
 * `/verification/runs/{id}`).
 *
 * Asynchronous: the start call answers `202` with the counters at zero; poll
 * `verification()->get($run->id)` until `isFinished()`. `updated` counts
 * addresses the provider actually answered for; `failed` counts lookups that
 * could not be made — a provider outage never rewrites a stored verdict.
 *
 * One run per project at a time: starting a second one while this is live is a
 * `409` `verification_already_running`.
 */
final readonly class VerificationRun
{
    public function __construct(
        public ?string $id,
        public ?string $provider,
        public int $total,
        public int $processed,
        public int $updated,
        public int $failed,
        public bool $finished,
        public ?string $startedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            provider: isset($data['provider']) ? (string) $data['provider'] : null,
            total: (int) ($data['total'] ?? 0),
            processed: (int) ($data['processed'] ?? 0),
            updated: (int) ($data['updated'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            finished: (bool) ($data['finished'] ?? false),
            startedAt: isset($data['started_at']) ? (string) $data['started_at'] : null,
        );
    }

    /**
     * Whether the run reached a terminal state, so polling can stop.
     */
    public function isFinished(): bool
    {
        return $this->finished;
    }
}
