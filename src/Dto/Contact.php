<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A contact. `lists` is only populated on the full profile endpoints
 * (GET /contacts/{email}, PATCH, list attach); listing items omit it.
 */
final readonly class Contact
{
    /**
     * @param array<string, mixed>      $attributes
     * @param array<int, Tag>           $tags
     * @param array<int, ContactList>   $lists
     */
    public function __construct(
        public ?string $uuid,
        public ?string $email,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $status,
        public ?string $locale,
        public array $attributes,
        public array $tags,
        public array $lists,
        public ?string $subscribedAt,
        public ?string $unsubscribedAt,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $tags = [];
        foreach ($data['tags'] ?? [] as $tag) {
            if (is_array($tag)) {
                $tags[] = Tag::fromArray($tag);
            }
        }

        $lists = [];
        foreach ($data['lists'] ?? [] as $list) {
            if (is_array($list)) {
                $lists[] = ContactList::fromArray($list);
            }
        }

        return new self(
            uuid: isset($data['uuid']) ? (string) $data['uuid'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            firstName: isset($data['first_name']) ? (string) $data['first_name'] : null,
            lastName: isset($data['last_name']) ? (string) $data['last_name'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            locale: isset($data['locale']) ? (string) $data['locale'] : null,
            attributes: is_array($data['attributes'] ?? null) ? $data['attributes'] : [],
            tags: $tags,
            lists: $lists,
            subscribedAt: isset($data['subscribed_at']) ? (string) $data['subscribed_at'] : null,
            unsubscribedAt: isset($data['unsubscribed_at']) ? (string) $data['unsubscribed_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
