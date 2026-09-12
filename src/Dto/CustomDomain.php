<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The project's own public hostname (`news.customer.com`).
 *
 * Once verified, public pages, webviews, tracking and unsubscribe links are
 * served on it; until then (and if it later breaks) everything falls back to
 * the project's platform subdomain, so links in already-sent mail keep working.
 *
 * Verification has two gates, both run by `check()`: the CNAME ownership proof
 * (`verificationError` `cname_missing`) and a TLS-live probe of
 * `https://{hostname}/up`. "DNS ok, no certificate yet" is its own state
 * (`tlsPending`), and a failed automated issuance is `tlsFailed` with a machine
 * reason in `tlsError` (`rate_limited`, `rejected`, `api_error`,
 * `certificate_failed`, `timeout`, `exhausted`).
 *
 * `verificationError` and `tlsError` stay MACHINE codes: an integration
 * branches on them, and a localized sentence is not a contract. A verified
 * domain is re-checked hourly and tolerates 3 consecutive failures
 * (`consecutiveFailures`) before it flips to `failed`; a failed domain keeps
 * being checked and heals back to verified silently.
 */
final readonly class CustomDomain
{
    public function __construct(
        public ?int $id,
        public ?string $hostname,
        public ?string $status,
        public ?bool $tlsPending,
        public ?bool $tlsFailed,
        public ?string $tlsError,
        public ?string $verificationError,
        public int $consecutiveFailures,
        public ?string $lastCheckedAt,
        public ?string $verifiedAt,
        public ?string $createdAt,
        public ?CustomDomainRecord $record,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            hostname: isset($data['hostname']) ? (string) $data['hostname'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            tlsPending: isset($data['tls_pending']) ? (bool) $data['tls_pending'] : null,
            tlsFailed: isset($data['tls_failed']) ? (bool) $data['tls_failed'] : null,
            tlsError: isset($data['tls_error']) ? (string) $data['tls_error'] : null,
            verificationError: isset($data['verification_error']) ? (string) $data['verification_error'] : null,
            consecutiveFailures: (int) ($data['consecutive_failures'] ?? 0),
            lastCheckedAt: isset($data['last_checked_at']) ? (string) $data['last_checked_at'] : null,
            verifiedAt: isset($data['verified_at']) ? (string) $data['verified_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            record: is_array($data['record'] ?? null) ? CustomDomainRecord::fromArray($data['record']) : null,
        );
    }

    /**
     * Whether both gates pass and the hostname is serving the project's public
     * pages.
     */
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
