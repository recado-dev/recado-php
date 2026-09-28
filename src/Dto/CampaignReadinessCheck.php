<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * One row of the pre-send checklist (GET /campaigns/{id}/readiness).
 *
 * A failing check's `code` is exactly the code POST /campaigns/{id}/send would
 * have failed with (`missing_subject`, `quota_exceeded`, ...), so a checklist
 * row maps onto a send failure without a second vocabulary. `meta` carries the
 * check's own context (quota numbers, the resolved from address, ...).
 *
 * `required` separates BLOCKING checks from ADVISORY ones (`estimated_cost`,
 * `warmup`, `risky_recipients`, `locale_variants`,
 * `from_defaults_to_transactional`): an advisory check always passes and can
 * never make a campaign unsendable, it only carries information in `meta`.
 * It is null when the API did not report it; `isAdvisory()` then falls back to
 * the known advisory keys.
 */
final readonly class CampaignReadinessCheck
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public ?string $key,
        public bool $passed,
        public ?string $code,
        public ?string $message,
        public array $meta,
        public ?bool $required = null,
    ) {}

    /**
     * The check keys the platform reports as advisory (never blocking).
     *
     * @var array<int, string>
     */
    public const array ADVISORY_KEYS = [
        'estimated_cost',
        'warmup',
        'risky_recipients',
        'locale_variants',
        'from_defaults_to_transactional',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: isset($data['key']) ? (string) $data['key'] : null,
            passed: (bool) ($data['passed'] ?? false),
            code: isset($data['code']) ? (string) $data['code'] : null,
            message: isset($data['message']) ? (string) $data['message'] : null,
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
            required: isset($data['required']) ? (bool) $data['required'] : null,
        );
    }

    /**
     * Whether this check is informational only and can never block a send.
     *
     * A `warmup` check that FAILS is the one blocking case of that key (the
     * identity is paused by the circuit breaker), so a failing check is never
     * reported as advisory when `required` is unknown.
     */
    public function isAdvisory(): bool
    {
        if ($this->required !== null) {
            return ! $this->required;
        }

        return $this->passed && in_array($this->key, self::ADVISORY_KEYS, true);
    }

    /**
     * One value out of `meta`, or `$default` when absent.
     */
    public function metaValue(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }
}
