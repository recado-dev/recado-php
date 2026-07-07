<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Result of a sandbox event simulation
 * (POST /sandbox/messages/{uuid}/events).
 */
final readonly class SimulatedEvent
{
    public function __construct(
        public ?string $message,
        public ?string $event,
        public ?string $status,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            message: isset($data['message']) ? (string) $data['message'] : null,
            event: isset($data['event']) ? (string) $data['event'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
        );
    }
}
