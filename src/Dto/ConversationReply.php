<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Result of replying to a conversation (POST /conversations/{uuid}/replies).
 *
 * `warnings` are non-blocking facts about the send — `inbound_not_active`
 * when the project's inbound replies are not active, so the person's next
 * answer will not come back to Recado.
 */
final readonly class ConversationReply
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public ?string $messageUuid,
        public ?string $status,
        public array $warnings,
    ) {}

    public function inboundNotActive(): bool
    {
        return in_array('inbound_not_active', $this->warnings, true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];

        return new self(
            messageUuid: isset($message['uuid']) ? (string) $message['uuid'] : null,
            status: isset($message['status']) ? (string) $message['status'] : null,
            warnings: array_values(array_map('strval', is_array($data['warnings'] ?? null) ? $data['warnings'] : [])),
        );
    }
}
