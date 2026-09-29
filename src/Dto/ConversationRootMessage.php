<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The email a conversation started from: the message the contact first
 * replied to. `template` is its template slug (null for inline sends).
 */
final readonly class ConversationRootMessage
{
    public function __construct(
        public ?string $uuid,
        public ?string $subject,
        public ?string $toEmail,
        public ?string $source,
        public ?string $template,
        public ?int $campaignId,
        public ?int $automationId,
        public ?string $sentAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: self::string($data, 'uuid'),
            subject: self::string($data, 'subject'),
            toEmail: self::string($data, 'to_email'),
            source: self::string($data, 'source'),
            template: self::string($data, 'template'),
            campaignId: isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            automationId: isset($data['automation_id']) ? (int) $data['automation_id'] : null,
            sentAt: self::string($data, 'sent_at'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function string(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : null;
    }
}
