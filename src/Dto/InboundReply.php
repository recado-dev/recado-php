<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A stored reply a contact sent to one of the project's emails
 * (GET /replies, GET /messages/{uuid}/replies, GET /contacts/{email}/replies).
 * `conversationUuid` names the Inbox conversation it belongs to.
 *
 * TEXT only: the HTML body and attachment binaries never leave the platform,
 * `attachments` is metadata (with `withheld` saying why a binary was not kept).
 * `strippedText` is the reply without the quoted thread (best effort — fall
 * back to `text`, see `body()`); both are capped and `truncated` says so.
 *
 * Unlike the `message.replied` webhook, this lists EVERY stored reply, so
 * check the flags: `authenticated` (the provider's verdicts add up to a
 * verified sender), `autoReply` (out-of-office and similar), `bounceReport`
 * (a delivery-status report arriving as mail) and `unparsable`.
 *
 * `messageUuid` is null once the replied-to message was deleted; the contact
 * fields are null when the sender matched no contact (a reply never creates one).
 */
final readonly class InboundReply
{
    /**
     * @param  array{spf: ?string, dkim: ?string, dmarc: ?string}  $auth
     * @param  array<int, MessageAttachment>  $attachments
     */
    public function __construct(
        public ?string $uuid,
        public ?string $messageUuid,
        public ?string $contactUuid,
        public ?string $contactEmail,
        public ?string $fromEmail,
        public ?string $fromName,
        public ?string $subject,
        public ?string $strippedText,
        public ?string $text,
        public ?string $receivedAt,
        public array $auth,
        public bool $authenticated,
        public bool $autoReply,
        public bool $bounceReport,
        public bool $truncated,
        public bool $unparsable,
        public array $attachments,
        public ?string $conversationUuid = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];
        $auth = is_array($data['auth'] ?? null) ? $data['auth'] : [];

        $attachments = [];
        foreach ($data['attachments'] ?? [] as $attachment) {
            if (is_array($attachment)) {
                $attachments[] = MessageAttachment::fromArray($attachment);
            }
        }

        return new self(
            uuid: self::string($data, 'uuid'),
            messageUuid: self::string($data, 'message_uuid'),
            contactUuid: self::string($contact, 'uuid'),
            contactEmail: self::string($contact, 'email'),
            fromEmail: self::string($data, 'from_email'),
            fromName: self::string($data, 'from_name'),
            subject: self::string($data, 'subject'),
            strippedText: self::string($data, 'stripped_text'),
            text: self::string($data, 'text'),
            receivedAt: self::string($data, 'received_at'),
            auth: [
                'spf' => self::string($auth, 'spf'),
                'dkim' => self::string($auth, 'dkim'),
                'dmarc' => self::string($auth, 'dmarc'),
            ],
            authenticated: (bool) ($data['authenticated'] ?? false),
            autoReply: (bool) ($data['auto_reply'] ?? false),
            bounceReport: (bool) ($data['bounce_report'] ?? false),
            truncated: (bool) ($data['truncated'] ?? false),
            unparsable: (bool) ($data['unparsable'] ?? false),
            attachments: $attachments,
            conversationUuid: self::string($data, 'conversation_uuid'),
        );
    }

    /**
     * The reply body to show: the stripped reply when there is one, else the
     * full text.
     */
    public function body(): ?string
    {
        return $this->strippedText !== null && $this->strippedText !== ''
            ? $this->strippedText
            : $this->text;
    }

    /**
     * Whether this is a human reply from a verified sender — the same rule
     * that decides whether `message.replied` fires.
     */
    public function isGenuine(): bool
    {
        return $this->authenticated && ! $this->autoReply && ! $this->bounceReport;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function string(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : null;
    }
}
