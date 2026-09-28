<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A message (an email the platform sent). `events` is only populated on the
 * single-message endpoint (GET /messages/{uuid}).
 *
 * `provider` names the BYO provider (and stream role) that delivered it; it is
 * null for platform delivery, sandbox interception, a message not sent yet or
 * one sent before this was recorded. `attachments` is metadata only.
 */
final readonly class Message
{
    /**
     * @param  array<int, MessageEvent>  $events
     * @param  array<int, string>|null  $cc
     * @param  array<int, string>|null  $bcc
     * @param  array<string, mixed>|null  $metadata
     * @param  array<int, MessageAttachment>  $attachments
     */
    public function __construct(
        public ?string $uuid,
        public ?string $toEmail,
        public ?string $fromEmail,
        public ?string $subject,
        public ?string $status,
        public ?string $source,
        public ?int $campaignId,
        public ?int $automationId,
        public ?string $error,
        public ?string $sentAt,
        public ?string $createdAt,
        public array $events,
        public ?array $cc = null,
        public ?array $bcc = null,
        public ?string $replyTo = null,
        public ?array $metadata = null,
        public ?MessageProvider $provider = null,
        public array $attachments = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $events = [];
        foreach ($data['events'] ?? [] as $event) {
            if (is_array($event)) {
                $events[] = MessageEvent::fromArray($event);
            }
        }

        $attachments = [];
        foreach ($data['attachments'] ?? [] as $attachment) {
            if (is_array($attachment)) {
                $attachments[] = MessageAttachment::fromArray($attachment);
            }
        }

        return new self(
            uuid: isset($data['uuid']) ? (string) $data['uuid'] : null,
            toEmail: isset($data['to_email']) ? (string) $data['to_email'] : null,
            fromEmail: isset($data['from_email']) ? (string) $data['from_email'] : null,
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            source: isset($data['source']) ? (string) $data['source'] : null,
            campaignId: isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            automationId: isset($data['automation_id']) ? (int) $data['automation_id'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null,
            sentAt: isset($data['sent_at']) ? (string) $data['sent_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            events: $events,
            cc: isset($data['cc']) && is_array($data['cc']) ? array_values($data['cc']) : null,
            bcc: isset($data['bcc']) && is_array($data['bcc']) ? array_values($data['bcc']) : null,
            replyTo: isset($data['reply_to']) ? (string) $data['reply_to'] : null,
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null,
            provider: is_array($data['provider'] ?? null) ? MessageProvider::fromArray($data['provider']) : null,
            attachments: $attachments,
        );
    }
}
