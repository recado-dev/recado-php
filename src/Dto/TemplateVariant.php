<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A per-locale template variant.
 */
final readonly class TemplateVariant
{
    public function __construct(
        public ?string $locale,
        public ?string $subject,
        public ?string $bodyHtml,
        public ?string $bodyText,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            locale: isset($data['locale']) ? (string) $data['locale'] : null,
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            bodyHtml: isset($data['body_html']) ? (string) $data['body_html'] : null,
            bodyText: isset($data['body_text']) ? (string) $data['body_text'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
