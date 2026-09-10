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
    ) {}

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
        );
    }
}
