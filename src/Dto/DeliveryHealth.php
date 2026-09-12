<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The project's sending-reputation snapshot (GET /delivery/health) — the
 * machine-readable twin of the dashboard's Delivery page.
 *
 * `domains` carries one block per VERIFIED sending identity (pending and
 * failed domains never carry traffic, so they are excluded). `sesAccount` is
 * null unless the project's active email provider is BYO Amazon SES.
 *
 * Not available in a sandbox: an intercepted project has no sending
 * reputation, so a sandbox token gets a `422` with the code
 * `not_available_in_sandbox`.
 */
final readonly class DeliveryHealth
{
    /**
     * @param  array<int, SendingDomainHealth>  $domains
     */
    public function __construct(
        public array $domains,
        public SuppressionPressure $suppressions,
        public ?SesAccountQuota $sesAccount,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $domains = [];

        foreach ($data['domains'] ?? [] as $domain) {
            if (is_array($domain)) {
                $domains[] = SendingDomainHealth::fromArray($domain);
            }
        }

        return new self(
            domains: $domains,
            suppressions: SuppressionPressure::fromArray(
                is_array($data['suppressions'] ?? null) ? $data['suppressions'] : [],
            ),
            sesAccount: is_array($data['ses_account'] ?? null)
                ? SesAccountQuota::fromArray($data['ses_account'])
                : null,
        );
    }

    /**
     * The identities the circuit breaker is currently holding.
     *
     * @return array<int, SendingDomainHealth>
     */
    public function pausedDomains(): array
    {
        return array_values(array_filter(
            $this->domains,
            static fn (SendingDomainHealth $domain): bool => $domain->isPaused(),
        ));
    }
}
