<?php

declare(strict_types=1);

namespace Recado\Sdk\Webhooks;

use Recado\Sdk\Dto\MessageAttachment;

/**
 * The `data` of a `message.replied` webhook: a contact replied to one of the
 * project's emails.
 *
 * - `inbound*` describe the stored reply. `strippedText` is the reply without
 *   the quoted history (best effort — fall back to `text`); both are capped
 *   and `truncated` says whether a cap cut them. The HTML body and attachment
 *   binaries are never sent, `attachments` is metadata only.
 * - `message*` describe the email that was replied to; `messageUuid` is null
 *   when it was deleted since. `messageMetadata` is the exact `metadata` map
 *   you attached on `/send` — the way to map a reply back to your own record.
 * - `contactUuid`/`contactEmail` are null when the sender is not a contact of
 *   the project (a reply never creates one).
 *
 * Only authenticated, non-automatic replies fire this event, exactly once per
 * stored reply. `auth` carries the provider's SPF/DKIM/DMARC verdicts.
 */
final readonly class MessageReplied
{
    /**
     * @param  array<int, MessageAttachment>  $attachments
     * @param  array{spf: ?string, dkim: ?string, dmarc: ?string}  $auth
     * @param  array<string, mixed>|null  $messageMetadata
     * @param  array<string, mixed>  $raw  The raw `data` block.
     */
    public function __construct(
        public ?string $inboundUuid,
        public ?string $receivedAt,
        public ?string $fromEmail,
        public ?string $fromName,
        public ?string $subject,
        public ?string $text,
        public ?string $strippedText,
        public bool $truncated,
        public array $attachments,
        public array $auth,
        public ?string $messageUuid,
        public ?string $messageSource,
        public ?string $messageTemplate,
        public ?int $messageCampaignId,
        public ?int $messageAutomationId,
        public ?array $messageMetadata,
        public ?string $messageSentAt,
        public ?string $contactUuid,
        public ?string $contactEmail,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The webhook `data` block.
     */
    public static function fromArray(array $data): self
    {
        $inbound = is_array($data['inbound'] ?? null) ? $data['inbound'] : [];
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];
        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];
        $auth = is_array($inbound['auth'] ?? null) ? $inbound['auth'] : [];

        $attachments = [];
        foreach ($inbound['attachments'] ?? [] as $attachment) {
            if (is_array($attachment)) {
                $attachments[] = MessageAttachment::fromArray($attachment);
            }
        }

        return new self(
            inboundUuid: self::string($inbound, 'uuid'),
            receivedAt: self::string($inbound, 'received_at'),
            fromEmail: self::string($inbound, 'from_email'),
            fromName: self::string($inbound, 'from_name'),
            subject: self::string($inbound, 'subject'),
            text: self::string($inbound, 'text'),
            strippedText: self::string($inbound, 'stripped_text'),
            truncated: (bool) ($inbound['truncated'] ?? false),
            attachments: $attachments,
            auth: [
                'spf' => self::string($auth, 'spf'),
                'dkim' => self::string($auth, 'dkim'),
                'dmarc' => self::string($auth, 'dmarc'),
            ],
            messageUuid: self::string($message, 'uuid'),
            messageSource: self::string($message, 'source'),
            messageTemplate: self::string($message, 'template'),
            messageCampaignId: isset($message['campaign_id']) ? (int) $message['campaign_id'] : null,
            messageAutomationId: isset($message['automation_id']) ? (int) $message['automation_id'] : null,
            messageMetadata: is_array($message['metadata'] ?? null) ? $message['metadata'] : null,
            messageSentAt: self::string($message, 'sent_at'),
            contactUuid: self::string($contact, 'uuid'),
            contactEmail: self::string($contact, 'email'),
            raw: $data,
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
     * @param  array<array-key, mixed>  $data
     */
    private static function string(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : null;
    }
}
