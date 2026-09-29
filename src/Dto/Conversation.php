<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * An Inbox conversation (GET /conversations, GET /conversations/{uuid}): the
 * thread rooted at one email a contact replied to, with the team's SHARED
 * read/archive state.
 *
 * `rootMessage` is the email the contact first replied to (null once it was
 * deleted — `subject` and `participantEmail` are snapshots that survive it).
 * A new human reply marks a conversation unread and brings it back from the
 * archive; auto-replies and bounce reports do neither. `readBy` / `archivedBy`
 * are `['id' => int, 'name' => string]` when a team member did it, null when an
 * API key or nobody did.
 *
 * `items` (the whole thread, oldest first) is only filled by `get()`.
 */
final readonly class Conversation
{
    /**
     * @param  array{id: int, name: string}|null  $readBy
     * @param  array{id: int, name: string}|null  $archivedBy
     * @param  array<int, ConversationItem>  $items
     */
    public function __construct(
        public ?string $uuid,
        public ?string $subject,
        public ?string $participantEmail,
        public ?string $contactUuid,
        public ?string $contactEmail,
        public ?ConversationRootMessage $rootMessage,
        public bool $unread,
        public ?string $readAt,
        public ?array $readBy,
        public bool $archived,
        public ?string $archivedAt,
        public ?array $archivedBy,
        public ?string $lastInboundAt,
        public ?string $lastOutboundAt,
        public ?string $lastActivityAt,
        public int $inboundCount,
        public int $outboundCount,
        public ?string $preview,
        public ?string $createdAt,
        public array $items = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];

        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            if (is_array($item)) {
                $items[] = ConversationItem::fromArray($item);
            }
        }

        return new self(
            uuid: self::string($data, 'uuid'),
            subject: self::string($data, 'subject'),
            participantEmail: self::string($data, 'participant_email'),
            contactUuid: self::string($contact, 'uuid'),
            contactEmail: self::string($contact, 'email'),
            rootMessage: is_array($data['root_message'] ?? null) ? ConversationRootMessage::fromArray($data['root_message']) : null,
            unread: (bool) ($data['unread'] ?? false),
            readAt: self::string($data, 'read_at'),
            readBy: self::user($data['read_by'] ?? null),
            archived: (bool) ($data['archived'] ?? false),
            archivedAt: self::string($data, 'archived_at'),
            archivedBy: self::user($data['archived_by'] ?? null),
            lastInboundAt: self::string($data, 'last_inbound_at'),
            lastOutboundAt: self::string($data, 'last_outbound_at'),
            lastActivityAt: self::string($data, 'last_activity_at'),
            inboundCount: (int) ($data['inbound_count'] ?? 0),
            outboundCount: (int) ($data['outbound_count'] ?? 0),
            preview: self::string($data, 'preview'),
            createdAt: self::string($data, 'created_at'),
            items: $items,
        );
    }

    /**
     * The received replies of the thread (only after `get()`).
     *
     * @return array<int, InboundReply>
     */
    public function replies(): array
    {
        $replies = [];
        foreach ($this->items as $item) {
            if ($item->reply !== null) {
                $replies[] = $item->reply;
            }
        }

        return $replies;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private static function user(mixed $value): ?array
    {
        if (! is_array($value) || ! isset($value['id'])) {
            return null;
        }

        return ['id' => (int) $value['id'], 'name' => isset($value['name']) && is_scalar($value['name']) ? (string) $value['name'] : ''];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function string(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : null;
    }
}
