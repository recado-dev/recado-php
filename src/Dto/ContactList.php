<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A contact list. Some fields (`description`, `contactsCount`, `createdAt`) are
 * only present on the dedicated lists endpoints; the abbreviated form embedded
 * in a contact profile carries just `id` and `name`.
 */
final readonly class ContactList
{
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $description,
        public ?int $contactsCount,
        public ?string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            contactsCount: isset($data['contacts_count']) ? (int) $data['contacts_count'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }
}
