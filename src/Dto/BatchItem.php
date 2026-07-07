<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A single per-message result inside a batch send response.
 */
final readonly class BatchItem
{
    public function __construct(
        public ?int $index,
        public ?string $status,
        public ?string $id,
        public ?string $code,
        public ?string $error,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            index: isset($data['index']) ? (int) $data['index'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            id: isset($data['id']) ? (string) $data['id'] : null,
            code: isset($data['code']) ? (string) $data['code'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }
}
