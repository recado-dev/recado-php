<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * One signup on a waiting list, with its LIVE position.
 *
 * Positions are never stored: every listing ranks the whole waitlist with a
 * window function (`referrals_count` desc, then signup order, then id), so a
 * referral credit re-ranks everyone for free. The `email` filter is applied
 * after ranking, so a matched member still reports the position it holds on the
 * full list.
 *
 * `referralCode` is not a secret — it is the code embedded in the member's own
 * public share link. `uuid` is the member's public status-page token.
 */
final readonly class WaitlistMember
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?int $contactId,
        public ?string $email,
        public int $position,
        public ?string $referralCode,
        public ?int $referredById,
        public int $referralsCount,
        public ?string $confirmedAt,
        public ?string $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            uuid: isset($data['uuid']) ? (string) $data['uuid'] : null,
            contactId: isset($data['contact_id']) ? (int) $data['contact_id'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            position: (int) ($data['position'] ?? 0),
            referralCode: isset($data['referral_code']) ? (string) $data['referral_code'] : null,
            referredById: isset($data['referred_by_id']) ? (int) $data['referred_by_id'] : null,
            referralsCount: (int) ($data['referrals_count'] ?? 0),
            confirmedAt: isset($data['confirmed_at']) ? (string) $data['confirmed_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }

    /**
     * Whether this signup came in through somebody else's referral link.
     */
    public function wasReferred(): bool
    {
        return $this->referredById !== null;
    }
}
