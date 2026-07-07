<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A template. The compact listing form omits `bodyHtml`/`bodyText`/`variants`;
 * the full form (GET/POST/PATCH single) populates them.
 */
final readonly class Template
{
    /**
     * @param array<int, TemplateVariant> $variants
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
    ) {
    }

    /**
     * @param array<string, mixed> $data
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
        );
    }
}
