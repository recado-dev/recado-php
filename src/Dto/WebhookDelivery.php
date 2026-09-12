<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * One delivery attempt of an outbound webhook endpoint
 * (GET /webhooks/{id}/deliveries).
 *
 * `status` is the HTTP status the receiver answered — `null` means no response
 * at all (timeout, connection error). The signed request payload is never
 * stored, so it is never returned here.
 */
final readonly class WebhookDelivery
{
    public function __construct(
        public ?int $id,
        public ?string $event,
        public ?int $status,
        public ?bool $success,
        public ?int $attempt,
        public ?string $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            event: isset($data['event']) ? (string) $data['event'] : null,
            status: isset($data['status']) ? (int) $data['status'] : null,
            success: isset($data['success']) ? (bool) $data['success'] : null,
            attempt: isset($data['attempt']) ? (int) $data['attempt'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }
}
