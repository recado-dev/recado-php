<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The metadata of one attachment a message was sent with. The binary itself
 * is never exposed (it is deleted once the message is sent); `size` is the
 * decoded size in bytes.
 */
final readonly class MessageAttachment
{
    public function __construct(
        public ?string $filename,
        public ?string $contentType,
        public ?int $size,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            filename: isset($data['filename']) ? (string) $data['filename'] : null,
            contentType: isset($data['content_type']) ? (string) $data['content_type'] : null,
            size: isset($data['size']) ? (int) $data['size'] : null,
        );
    }
}
