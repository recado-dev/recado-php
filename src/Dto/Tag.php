<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A tag. `contactsCount` is only present on the GET /tags listing.
 */
final readonly class Tag
{
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $color,
        public ?int $contactsCount,
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
            color: isset($data['color']) ? (string) $data['color'] : null,
            contactsCount: isset($data['contacts_count']) ? (int) $data['contacts_count'] : null,
        );
    }
}
