<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Result of a push device token operation (register/remove).
 *
 * Fields are per-operation nullable: register populates `registered`/`devices`,
 * remove populates `removed`.
 */
final readonly class PushTokenResult
{
    public function __construct(
        public ?bool $registered,
        public ?int $devices,
        public ?bool $removed,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            registered: isset($data['registered']) ? (bool) $data['registered'] : null,
            devices: isset($data['devices']) ? (int) $data['devices'] : null,
            removed: isset($data['removed']) ? (bool) $data['removed'] : null,
        );
    }
}
