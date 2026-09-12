<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A tag. `contactsCount` is only present on the GET /tags listing.
 *
 * The `public*` trio is the preference-center surface: with `isPublic` on, the
 * tag shows on the project's preference center as an opt-in checkbox labelled
 * by `publicLabel` (falling back to the name).
 */
final readonly class Tag
{
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $color,
        public ?int $contactsCount,
        public ?bool $isPublic = null,
        public ?string $publicLabel = null,
        public ?string $publicDescription = null,
        public ?string $createdAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            color: isset($data['color']) ? (string) $data['color'] : null,
            contactsCount: isset($data['contacts_count']) ? (int) $data['contacts_count'] : null,
            isPublic: isset($data['is_public']) ? (bool) $data['is_public'] : null,
            publicLabel: isset($data['public_label']) ? (string) $data['public_label'] : null,
            publicDescription: isset($data['public_description']) ? (string) $data['public_description'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }
}
