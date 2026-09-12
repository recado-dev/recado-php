<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

use RuntimeException;
use Throwable;

/**
 * Base exception for every error surfaced by the Recado SDK.
 *
 * Every exception exposes the API machine code, the HTTP status and the raw
 * decoded response body so callers can branch on stable, programmatic values
 * instead of parsing human-facing messages.
 */
class RecadoException extends RuntimeException
{
    /**
     * The machine code production-only endpoints answer a SANDBOX credential
     * with. Matched on the code rather than the HTTP status on purpose: the
     * platform is unifying those refusals on `404` (they used to be a mix of
     * `404` and `422`), and a caller must not have to care which one it got.
     */
    public const string NOT_AVAILABLE_IN_SANDBOX = 'not_available_in_sandbox';

    /**
     * Whether the request was refused because the credential belongs to a
     * sandbox twin and the endpoint is production-only (sending domains, custom
     * domains, sender health).
     *
     * A sandbox never sends externally and never serves customer-facing pages,
     * so it has no sending identities, no public hostname and no sending
     * reputation — and its token must not reach the production project's.
     */
    public function isNotAvailableInSandbox(): bool
    {
        return $this->errorCode === self::NOT_AVAILABLE_IN_SANDBOX;
    }

    /**
     * @param  array<string, mixed>|null  $body  The raw decoded response envelope.
     */
    public function __construct(
        string $message,
        private readonly ?string $errorCode = null,
        private readonly ?int $status = null,
        private readonly ?array $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The machine-readable error code returned by the API (the `code` field),
     * or null when the response did not carry one.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * The HTTP status code of the failed response.
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * The raw decoded response body, or null when the body was empty/unparseable.
     *
     * @return array<string, mixed>|null
     */
    public function getBody(): ?array
    {
        return $this->body;
    }
}
