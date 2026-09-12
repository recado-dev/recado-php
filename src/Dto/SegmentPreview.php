<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The dry-run result of a segment conditions tree (POST `/segments/preview`).
 *
 * Answers "what would this target?" without creating anything: no segment row
 * is written, and nothing is left to clean up when the answer is not what you
 * meant. Validation is byte-identical to `segments()->create()`, so a tree that
 * previews cleanly is one create will accept.
 *
 * The `sample` carries the same contact shape `contacts()->list()` returns,
 * newest first, and is always scoped to the authenticated project.
 */
final readonly class SegmentPreview
{
    /**
     * @param  array<int, Contact>  $sample
     */
    public function __construct(
        public int $contactsCount,
        public array $sample,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $sample = [];

        foreach ($data['sample'] ?? [] as $contact) {
            if (is_array($contact)) {
                $sample[] = Contact::fromArray($contact);
            }
        }

        return new self(
            contactsCount: (int) ($data['contacts_count'] ?? 0),
            sample: $sample,
        );
    }
}
