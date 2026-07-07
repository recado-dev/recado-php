<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

use Throwable;

/**
 * Thrown on HTTP 422 responses (validation failures and domain-level rejections
 * such as recipient_suppressed, quota_exceeded, template_not_found, ...).
 */
class ValidationException extends RecadoException
{
    /**
     * @param array<string, array<int, string>> $errors Field => list of messages.
     * @param array<string, mixed>|null         $body   The raw decoded response envelope.
     */
    public function __construct(
        string $message,
        private readonly array $errors = [],
        ?string $errorCode = null,
        ?int $status = null,
        ?array $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode, $status, $body, $previous);
    }

    /**
     * The per-field validation messages (the `errors` map), keyed by field name.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
