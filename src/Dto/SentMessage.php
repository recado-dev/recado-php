<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Result of a single transactional send (POST /send).
 */
final readonly class SentMessage
{
    public function __construct(
        public ?string $id,
        public ?string $status,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
        );
    }
}
