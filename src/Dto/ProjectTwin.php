<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The sandbox twin of a production project — the paired project where every
 * send is intercepted and nothing leaves the platform.
 *
 * A sandbox has no twin of its own (sandbox-of-a-sandbox is prevented), so
 * `ProjectProfile::$sandboxTwin` is always null when the key is a sandbox key.
 */
final readonly class ProjectTwin
{
    public function __construct(
        public ?string $name,
        public ?string $uuid,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            uuid: isset($data['uuid']) ? (string) $data['uuid'] : null,
        );
    }
}
