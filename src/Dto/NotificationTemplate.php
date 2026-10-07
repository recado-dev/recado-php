<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A notification template: reusable in-app/push content, addressed by `slug`
 * — the identifier `notifications()->send()` accepts as `template`.
 *
 * The compact listing form omits `body`, `actionUrl`, `icon`, `push` and
 * `variants`; the full form (GET/POST/PATCH single) populates them.
 *
 * `push` is the optional object of native push extras (sound, badge,
 * category, thread_id, interruption_level, android_channel_id, data, silent)
 * the push part of a send uses; null when the template carries none.
 */
final readonly class NotificationTemplate
{
    /**
     * @param  array<int, NotificationTemplateVariant>  $variants
     * @param  array<string, mixed>|null  $push
     */
    public function __construct(
        public ?string $slug,
        public ?string $name,
        public ?string $title,
        public ?string $body,
        public ?string $actionUrl,
        public ?string $icon,
        public array $variants,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?array $push = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $variants = [];

        foreach ($data['variants'] ?? [] as $variant) {
            if (is_array($variant)) {
                $variants[] = NotificationTemplateVariant::fromArray($variant);
            }
        }

        return new self(
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            body: isset($data['body']) ? (string) $data['body'] : null,
            actionUrl: isset($data['action_url']) ? (string) $data['action_url'] : null,
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            variants: $variants,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            push: is_array($data['push'] ?? null) ? $data['push'] : null,
        );
    }
}
