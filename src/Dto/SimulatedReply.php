<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Result of a sandbox reply simulation
 * (POST /sandbox/messages/{uuid}/reply).
 *
 * `fired` says whether the reply drove the `message.replied` webhook and
 * `contact_replied` automations: only an authenticated reply that is not an
 * auto-reply or a bounce report does.
 */
final readonly class SimulatedReply
{
    public function __construct(
        public ?string $message,
        public ?string $uuid,
        public ?string $fromEmail,
        public ?string $contact,
        public bool $authenticated,
        public bool $autoReply,
        public bool $bounceReport,
        public bool $fired,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $reply */
        $reply = is_array($data['reply'] ?? null) ? $data['reply'] : [];

        return new self(
            message: isset($data['message']) ? (string) $data['message'] : null,
            uuid: isset($reply['uuid']) ? (string) $reply['uuid'] : null,
            fromEmail: isset($reply['from_email']) ? (string) $reply['from_email'] : null,
            contact: isset($reply['contact']) ? (string) $reply['contact'] : null,
            authenticated: (bool) ($reply['authenticated'] ?? false),
            autoReply: (bool) ($reply['auto_reply'] ?? false),
            bounceReport: (bool) ($reply['bounce_report'] ?? false),
            fired: (bool) ($reply['fired'] ?? false),
        );
    }
}
