<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

use Throwable;

/**
 * Thrown on HTTP 429 responses (rate limit exceeded). Exposes the parsed
 * Retry-After hint in seconds when the server provided one.
 */
class RateLimitException extends RecadoException
{
    /**
     * @param array<string, mixed>|null $body The raw decoded response envelope.
     */
    public function __construct(
        string $message,
        private readonly ?int $retryAfter = null,
        ?string $errorCode = null,
        ?int $status = null,
        ?array $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode, $status, $body, $previous);
    }

    /**
     * The number of seconds to wait before retrying, parsed from the
     * Retry-After response header, or null when absent.
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
