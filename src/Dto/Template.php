<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A template. The compact listing form omits `bodyHtml`/`bodyText`/`variants`;
 * the full form (GET/POST/PATCH single) populates them.
 *
 * `minResendIntervalMinutes` is the per-template resend guard (1..1440, null =
 * off): a send of this template to a contact that already received it within
 * the window is refused with `template_resend_too_soon` (see
 * `TemplateResendTooSoonException`). Write it through `templates()->create()` /
 * `update()` with the `min_resend_interval_minutes` key.
 */
final readonly class Template
{
    /**
     * @param  array<int, TemplateVariant>  $variants
     */
    public function __construct(
        public ?string $slug,
        public ?string $name,
        public ?string $subject,
        public ?string $bodyHtml,
        public ?string $bodyText,
        public array $variants,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?int $minResendIntervalMinutes = null,
        public ?string $editor = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $variants = [];
        foreach ($data['variants'] ?? [] as $variant) {
            if (is_array($variant)) {
                $variants[] = TemplateVariant::fromArray($variant);
            }
        }

        return new self(
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            bodyHtml: isset($data['body_html']) ? (string) $data['body_html'] : null,
            bodyText: isset($data['body_text']) ? (string) $data['body_text'] : null,
            variants: $variants,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            minResendIntervalMinutes: isset($data['min_resend_interval_minutes'])
                ? (int) $data['min_resend_interval_minutes']
                : null,
            editor: isset($data['editor']) ? (string) $data['editor'] : null,
        );
    }
}
