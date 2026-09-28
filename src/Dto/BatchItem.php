<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A single per-message result inside a batch send response.
 *
 * `retryAfterSeconds` is only set on a `template_resend_too_soon` item (the
 * per-template resend guard refused this recipient): the seconds until the
 * template may be sent to that contact again.
 */
final readonly class BatchItem
{
    public function __construct(
        public ?int $index,
        public ?string $status,
        public ?string $id,
        public ?string $code,
        public ?string $error,
        public ?int $retryAfterSeconds = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            index: isset($data['index']) ? (int) $data['index'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            id: isset($data['id']) ? (string) $data['id'] : null,
            code: isset($data['code']) ? (string) $data['code'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null,
            retryAfterSeconds: isset($data['retry_after_seconds']) ? (int) $data['retry_after_seconds'] : null,
        );
    }
}
