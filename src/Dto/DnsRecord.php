<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * One DNS record a sending domain needs published, shaped for a DNS panel
 * rather than for a mail engineer: `host` is relative to the zone (what most
 * panels want) with the absolute `fqdn` alongside, an MX `priority` is split
 * out of the value into its own field, and `ttl` is a concrete suggestion.
 *
 * `state` is the part worth polling, because it blends the provider's truth
 * with the platform's OWN live DNS lookups:
 *
 * - `verified` — the provider confirms it. The only source of truth for sending.
 * - `detected` — our resolver already sees the expected value, the provider is
 *   still pending. Reassuring; never promoted to `verified` on our detection alone.
 * - `not_found` — our resolver does not see it yet. The only "your turn" state.
 * - `failed` — the provider reports failure.
 *
 * `kind` is `dkim`, `mail_from_mx` or `mail_from_spf`; it is null on the DMARC
 * recommendation, which uses this same shape.
 */
final readonly class DnsRecord
{
    public function __construct(
        public ?string $type,
        public ?string $host,
        public ?string $fqdn,
        public ?string $value,
        public ?int $priority,
        public ?string $ttl,
        public ?string $kind,
        public ?string $status,
        public ?string $state,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: isset($data['type']) ? (string) $data['type'] : null,
            host: isset($data['host']) ? (string) $data['host'] : null,
            fqdn: isset($data['fqdn']) ? (string) $data['fqdn'] : null,
            value: isset($data['value']) ? (string) $data['value'] : null,
            priority: isset($data['priority']) && is_numeric($data['priority']) ? (int) $data['priority'] : null,
            ttl: isset($data['ttl']) ? (string) $data['ttl'] : null,
            kind: isset($data['kind']) ? (string) $data['kind'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            state: isset($data['state']) ? (string) $data['state'] : null,
        );
    }

    /**
     * Whether the record still has to be published — the only "your turn"
     * state. A `detected` record is already out there; the provider just has
     * not caught up.
     */
    public function isMissing(): bool
    {
        return $this->state === 'not_found';
    }
}
