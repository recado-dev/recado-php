<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * One recorded occurrence of a tracked event — the read side of POST /track.
 *
 * `data` is the payload exactly as it was submitted (an empty array when the
 * call carried none); the event and contact blocks are flattened into scalar
 * properties so consumers never index into nested arrays.
 */
final readonly class EventOccurrence
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public ?int $id,
        public ?int $eventId,
        public ?string $eventName,
        public ?string $contactUuid,
        public ?string $contactEmail,
        public array $data,
        public ?string $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];
        $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];

        return new self(
            id: isset($payload['id']) ? (int) $payload['id'] : null,
            eventId: isset($event['id']) ? (int) $event['id'] : null,
            eventName: isset($event['name']) ? (string) $event['name'] : null,
            contactUuid: isset($contact['uuid']) ? (string) $contact['uuid'] : null,
            contactEmail: isset($contact['email']) ? (string) $contact['email'] : null,
            data: is_array($payload['data'] ?? null) ? $payload['data'] : [],
            createdAt: isset($payload['created_at']) ? (string) $payload['created_at'] : null,
        );
    }
}
