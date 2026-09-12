<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The outcome of cleaning a contact list (POST `/lists/{id}/clean`).
 *
 * Membership only: contacts are never deleted, never change status, keep their
 * other lists, and no subscription event or outbound webhook fires — membership
 * is not consent.
 *
 * Above the platform's inline threshold the same work is QUEUED instead of run
 * in the request: `queued` is then true and `removed` is null, with `matching`
 * carrying the count the job will work through. Anything that reads `removed`
 * has to handle that null.
 */
final readonly class ListCleanResult
{
    /**
     * @param  array<int, string>  $statuses  The statuses that were cleaned.
     */
    public function __construct(
        public ?int $listId,
        public array $statuses,
        public bool $invalidEmails,
        public int $matching,
        public ?int $removed,
        public bool $queued,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $statuses = [];

        foreach ($data['statuses'] ?? [] as $status) {
            if (is_scalar($status)) {
                $statuses[] = (string) $status;
            }
        }

        return new self(
            listId: isset($data['list_id']) ? (int) $data['list_id'] : null,
            statuses: $statuses,
            invalidEmails: (bool) ($data['invalid_emails'] ?? false),
            matching: (int) ($data['matching'] ?? 0),
            removed: isset($data['removed']) ? (int) $data['removed'] : null,
            queued: (bool) ($data['queued'] ?? false),
        );
    }
}
