<?php

declare(strict_types=1);

namespace Recado\Sdk\Webhooks;

/**
 * The `data` of an `identity.breaker_resumed` webhook: a paused sending
 * domain was resumed — by a person, by an operator command, or automatically
 * after the cooldown.
 *
 * - `resumedBy` is `auto` (the pause was caused by bounces only and its
 *   cooldown was over) or `manual`.
 * - `actor` is the actor TYPE: `user` (dashboard), `command` (operator CLI)
 *   or `system` (always for `auto`). The payload never names the person.
 * - `restoredStatus` is the warm-up status the identity went back to;
 *   `resumedCampaigns` / `resumedAutomationSends` are what the resume woke up.
 *
 * After an `auto` resume, a new pause within 7 days will NOT resume
 * automatically: expect an `identity.breaker_tripped` whose auto-resume
 * reason is `auto_resume_used`.
 */
final readonly class IdentityBreakerResumed
{
    /**
     * @param  array<string, mixed>  $raw  The raw `data` block.
     */
    public function __construct(
        public ?string $domain,
        public ?string $resumedBy,
        public ?string $actor,
        public ?string $trippedAt,
        public ?string $resumedAt,
        public ?string $restoredStatus,
        public int $resumedCampaigns,
        public int $resumedAutomationSends,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The webhook `data` block.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            domain: isset($data['domain']) ? (string) $data['domain'] : null,
            resumedBy: isset($data['resumed_by']) ? (string) $data['resumed_by'] : null,
            actor: isset($data['actor']) ? (string) $data['actor'] : null,
            trippedAt: isset($data['tripped_at']) ? (string) $data['tripped_at'] : null,
            resumedAt: isset($data['resumed_at']) ? (string) $data['resumed_at'] : null,
            restoredStatus: isset($data['restored_status']) ? (string) $data['restored_status'] : null,
            resumedCampaigns: (int) ($data['resumed_campaigns'] ?? 0),
            resumedAutomationSends: (int) ($data['resumed_automation_sends'] ?? 0),
            raw: $data,
        );
    }

    /**
     * Whether the pause was lifted automatically (nobody acted).
     */
    public function isAutomatic(): bool
    {
        return $this->resumedBy === 'auto';
    }
}
