<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A bulk contact-import run (POST /imports, GET /imports[/{id}]).
 *
 * An import is ASYNCHRONOUS: the create call answers `202` with `status`
 * `pending`; poll until it is `completed` or `failed`. `processedRows` is the
 * progress bar, `createdRows` + `updatedRows` are the contacts written, and
 * `skippedRows` counts rows that produced none (blank or malformed address, or
 * one skipped by the verification toggles).
 *
 * `invalidStatusRows`, `invalidEmailRows` and `riskyEmailRows` are WARNINGS:
 * the run completed, but those rows carried something the importer could not
 * take at face value. A run that could not start at all ends `failed` with a
 * human `error`.
 */
final readonly class Import
{
    /**
     * @param  array<int, int>  $lists
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public ?int $id,
        public ?string $status,
        public ?string $source,
        public array $lists,
        public array $tags,
        public ?bool $skipInvalidEmails,
        public ?bool $skipRiskyEmails,
        public int $processedRows,
        public int $createdRows,
        public int $updatedRows,
        public int $subscribedRows,
        public int $unsubscribedRows,
        public int $invalidStatusRows,
        public int $invalidEmailRows,
        public int $riskyEmailRows,
        public int $skippedRows,
        public ?string $error,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $lists = [];

        foreach ($data['lists'] ?? [] as $listId) {
            if (is_numeric($listId)) {
                $lists[] = (int) $listId;
            }
        }

        $tags = [];

        foreach ($data['tags'] ?? [] as $tag) {
            if (is_scalar($tag)) {
                $tags[] = (string) $tag;
            }
        }

        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            source: isset($data['source']) ? (string) $data['source'] : null,
            lists: $lists,
            tags: $tags,
            skipInvalidEmails: isset($data['skip_invalid_emails']) ? (bool) $data['skip_invalid_emails'] : null,
            skipRiskyEmails: isset($data['skip_risky_emails']) ? (bool) $data['skip_risky_emails'] : null,
            processedRows: (int) ($data['processed_rows'] ?? 0),
            createdRows: (int) ($data['created_rows'] ?? 0),
            updatedRows: (int) ($data['updated_rows'] ?? 0),
            subscribedRows: (int) ($data['subscribed_rows'] ?? 0),
            unsubscribedRows: (int) ($data['unsubscribed_rows'] ?? 0),
            invalidStatusRows: (int) ($data['invalid_status_rows'] ?? 0),
            invalidEmailRows: (int) ($data['invalid_email_rows'] ?? 0),
            riskyEmailRows: (int) ($data['risky_email_rows'] ?? 0),
            skippedRows: (int) ($data['skipped_rows'] ?? 0),
            error: isset($data['error']) ? (string) $data['error'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }

    /**
     * Whether the run reached a terminal state, so polling can stop.
     */
    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }
}
