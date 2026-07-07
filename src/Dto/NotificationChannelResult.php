<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A single per-channel result inside a notification send response
 * (POST /notifications).
 */
final readonly class NotificationChannelResult
{
    public function __construct(
        public ?string $channel,
        public ?string $id,
        public ?string $status,
        public ?string $errorCode,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channel: isset($data['channel']) ? (string) $data['channel'] : null,
            id: isset($data['id']) ? (string) $data['id'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            errorCode: isset($data['error_code']) ? (string) $data['error_code'] : null,
        );
    }

    /**
     * Whether this channel was accepted for delivery.
     */
    public function queued(): bool
    {
        return $this->status === 'queued';
    }
}
