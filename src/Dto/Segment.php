<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A dynamic segment: a saved query over the project's contacts.
 *
 * `conditions` stays a plain array — the SDK deliberately ships no query DSL,
 * so the condition tree is passed through exactly as the API documents it.
 * `contactsCount` is only present on the single-segment endpoints
 * (GET/POST/PATCH /segments/{id}); the paginated list omits it because
 * counting runs the compiled query per segment.
 */
final readonly class Segment
{
    /**
     * @param  array<string, mixed>  $conditions
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public array $conditions,
        public ?int $contactsCount,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            conditions: is_array($data['conditions'] ?? null) ? $data['conditions'] : [],
            contactsCount: isset($data['contacts_count']) ? (int) $data['contacts_count'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
