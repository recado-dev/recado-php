<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A per-locale notification-template variant.
 *
 * A variant is a FULL alternative to the base content, not a merge: its empty
 * `actionUrl`/`icon` win over the base values. At send time the recipient's
 * locale resolves it (exact tag, then language prefix), falling back to the
 * project default locale and finally to the base template.
 */
final readonly class NotificationTemplateVariant
{
    public function __construct(
        public ?string $locale,
        public ?string $title,
        public ?string $body,
        public ?string $actionUrl,
        public ?string $icon,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            locale: isset($data['locale']) ? (string) $data['locale'] : null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            body: isset($data['body']) ? (string) $data['body'] : null,
            actionUrl: isset($data['action_url']) ? (string) $data['action_url'] : null,
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
