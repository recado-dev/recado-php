<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * One entry of a conversation thread: an `inbound` item carries the received
 * `reply` (text only), an `outbound` item the `message` sent from Recado.
 */
final readonly class ConversationItem
{
    public const string TypeInbound = 'inbound';

    public const string TypeOutbound = 'outbound';

    public function __construct(
        public string $type,
        public ?string $at,
        public ?InboundReply $reply,
        public ?Message $message,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: isset($data['type']) && is_scalar($data['type']) ? (string) $data['type'] : self::TypeInbound,
            at: isset($data['at']) && is_scalar($data['at']) ? (string) $data['at'] : null,
            reply: is_array($data['reply'] ?? null) ? InboundReply::fromArray($data['reply']) : null,
            message: is_array($data['message'] ?? null) ? Message::fromArray($data['message']) : null,
        );
    }

    public function isInbound(): bool
    {
        return $this->type === self::TypeInbound;
    }
}
