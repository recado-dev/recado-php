<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The real-time verdict for one address (POST `/verify`).
 *
 * The same verdict a contact with this address would carry: `status` is one
 * of `valid`, `risky`, `invalid` or `unknown`. Nothing is stored on the
 * platform — no contact is created.
 *
 * `didYouMean` is the full corrected address for a likely typo
 * (`ana@gmial.com` → `ana@gmail.com`), ready for a "Did you mean …?" prompt.
 * `mxFound` is null when it could not be determined (bad syntax, or a DNS
 * lookup that ran out of time — which is also why an `unknown` verdict should
 * never block a signup). `smtpStatus` / `smtpCheckedAt` carry an answer an
 * earlier background SMTP probe cached (`accepted`, `rejected`, `catch_all`);
 * the request never probes.
 */
final readonly class EmailVerification
{
    /**
     * @param  array<int, string>  $reasons
     */
    public function __construct(
        public string $email,
        public string $status,
        public array $reasons = [],
        public ?string $didYouMean = null,
        public bool $disposable = false,
        public bool $role = false,
        public ?bool $mxFound = null,
        public ?string $smtpStatus = null,
        public ?string $smtpCheckedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $smtp = is_array($data['smtp'] ?? null) ? $data['smtp'] : null;

        return new self(
            email: (string) ($data['email'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            reasons: array_values(array_map('strval', is_array($data['reasons'] ?? null) ? $data['reasons'] : [])),
            didYouMean: isset($data['did_you_mean']) ? (string) $data['did_you_mean'] : null,
            disposable: (bool) ($data['disposable'] ?? false),
            role: (bool) ($data['role'] ?? false),
            mxFound: isset($data['mx_found']) ? (bool) $data['mx_found'] : null,
            smtpStatus: isset($smtp['status']) ? (string) $smtp['status'] : null,
            smtpCheckedAt: isset($smtp['checked_at']) ? (string) $smtp['checked_at'] : null,
        );
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function isRisky(): bool
    {
        return $this->status === 'risky';
    }

    /**
     * The address cannot receive mail (malformed, no mail route, or a
     * mailbox an SMTP probe saw rejected).
     */
    public function isInvalid(): bool
    {
        return $this->status === 'invalid';
    }

    public function isUnknown(): bool
    {
        return $this->status === 'unknown';
    }

    public function hasReason(string $reason): bool
    {
        return in_array($reason, $this->reasons, true);
    }
}
