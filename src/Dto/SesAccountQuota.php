<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A BYO-SES tenant's own Amazon SES account quota (GET /delivery/health).
 *
 * `available` false means the tenant's AWS account could not be read right now
 * — every other field is null then. That is deliberately distinct from the
 * whole block being absent, which means the project has no BYO SES provider.
 * `max24h` is null on an unlimited account.
 */
final readonly class SesAccountQuota
{
    public function __construct(
        public bool $available,
        public ?float $sentLast24h = null,
        public ?float $max24h = null,
        public ?float $maxSendRate = null,
        public ?bool $sendingEnabled = null,
        public ?string $enforcementStatus = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (($data['available'] ?? false) !== true) {
            return new self(available: false);
        }

        return new self(
            available: true,
            sentLast24h: isset($data['sent_last_24h']) ? (float) $data['sent_last_24h'] : null,
            max24h: isset($data['max_24h']) ? (float) $data['max_24h'] : null,
            maxSendRate: isset($data['max_send_rate']) ? (float) $data['max_send_rate'] : null,
            sendingEnabled: isset($data['sending_enabled']) ? (bool) $data['sending_enabled'] : null,
            enforcementStatus: isset($data['enforcement_status']) ? (string) $data['enforcement_status'] : null,
        );
    }
}
