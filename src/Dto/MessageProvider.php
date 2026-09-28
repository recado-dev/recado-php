<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Which of the project's own (BYO) email providers delivered a message, and
 * in which stream role, stamped at send time.
 *
 * `type` is the provider type (`ses`, `cloudflare`, ...) and `stream` the role
 * that provider played: `all` (one provider for every email), `transactional`
 * or `marketing` (a project that splits its streams across two providers).
 */
final readonly class MessageProvider
{
    public function __construct(
        public ?string $type,
        public ?string $stream,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: isset($data['type']) ? (string) $data['type'] : null,
            stream: isset($data['stream']) ? (string) $data['stream'] : null,
        );
    }
}
