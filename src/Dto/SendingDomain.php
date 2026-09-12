<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A sending identity of the project (GET/POST `/sending-domains`).
 *
 * `provider` names the provider the identity lives on — null is the platform's
 * shared Amazon SES account, otherwise `ses`, `postmark`, `mailgun` or
 * `resend`. **Scopes never cross**: a domain verified on one provider does not
 * satisfy another, which is why the record is stamped with the provider it was
 * created on and keeps being checked there even after you switch providers.
 *
 * `records` is the list to publish and `dmarc` is a RECOMMENDATION, never a
 * requirement (DMARC has no provider check, so our own detection is its
 * status). Because every representation performs live DNS lookups, treat these
 * endpoints as a status poll, not a hot path.
 *
 * `warmup` mirrors the block the dashboard renders for a ramping identity
 * (`status`, `previous_status`, `day_number`, `cap`, `sent_today`, `schedule`,
 * `day_index` and, on a breaker trip, `breaker_tripped_at`/`breaker_reason`).
 * It is null for an identity that is not warm-up limited. Skipping a ramp and
 * resuming a breaker-paused identity are judgement calls about sending
 * reputation and stay HUMAN actions in the dashboard — there is no API for
 * them, so the SDK exposes none.
 */
final readonly class SendingDomain
{
    /**
     * @param  array<int, DnsRecord>  $records
     * @param  array<string, mixed>|null  $warmup
     */
    public function __construct(
        public ?int $id,
        public ?string $domain,
        public ?string $provider,
        public ?string $status,
        public ?string $verificationError,
        public ?string $mailFromDomain,
        public ?string $mailFromStatus,
        public ?string $verifiedAt,
        public ?string $createdAt,
        public array $records,
        public ?DnsRecord $dmarc,
        public ?array $warmup,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $records = [];

        foreach ($data['records'] ?? [] as $record) {
            if (is_array($record)) {
                $records[] = DnsRecord::fromArray($record);
            }
        }

        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            domain: isset($data['domain']) ? (string) $data['domain'] : null,
            provider: isset($data['provider']) ? (string) $data['provider'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            verificationError: isset($data['verification_error']) ? (string) $data['verification_error'] : null,
            mailFromDomain: isset($data['mail_from_domain']) ? (string) $data['mail_from_domain'] : null,
            mailFromStatus: isset($data['mail_from_status']) ? (string) $data['mail_from_status'] : null,
            verifiedAt: isset($data['verified_at']) ? (string) $data['verified_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            records: $records,
            dmarc: is_array($data['dmarc'] ?? null) ? DnsRecord::fromArray($data['dmarc']) : null,
            warmup: is_array($data['warmup'] ?? null) ? $data['warmup'] : null,
        );
    }

    /**
     * Whether the provider confirms the identity — the only state that allows
     * sending from it.
     */
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * The records our resolver cannot see yet: what is still on the customer's
     * side to publish. A `detected` record is deliberately not included.
     *
     * @return array<int, DnsRecord>
     */
    public function missingRecords(): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (DnsRecord $record): bool => $record->isMissing(),
        ));
    }
}
