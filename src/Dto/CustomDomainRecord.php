<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The CNAME a custom domain has to publish to prove ownership.
 *
 * It is derived from the project's platform subdomain at read time — never
 * stored — so a renamed subdomain always reports the target that actually
 * verifies. A-records deliberately do not verify.
 */
final readonly class CustomDomainRecord
{
    public function __construct(
        public ?string $type,
        public ?string $name,
        public ?string $value,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: isset($data['type']) ? (string) $data['type'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            value: isset($data['value']) ? (string) $data['value'] : null,
        );
    }
}
