<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A single event on a message timeline (delivered, opened, clicked, ...).
 */
final readonly class MessageEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public ?string $type,
        public array $payload,
        public ?string $occurredAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: isset($data['type']) ? (string) $data['type'] : null,
            payload: is_array($data['payload'] ?? null) ? $data['payload'] : [],
            occurredAt: isset($data['occurred_at']) ? (string) $data['occurred_at'] : null,
        );
    }
}
