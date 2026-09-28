<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

/**
 * Thrown locally, with no HTTP request involved, when an incoming Recado
 * webhook cannot be trusted or read: the `X-Recado-Signature` header is
 * missing or does not match the raw body (`invalid_webhook_signature`), or
 * the verified body is not a Recado webhook envelope (`invalid_webhook_payload`).
 *
 * Answer such a request with a 4xx and do not act on it.
 */
final class WebhookVerificationException extends RecadoException
{
    public const string INVALID_SIGNATURE = 'invalid_webhook_signature';

    public const string INVALID_PAYLOAD = 'invalid_webhook_payload';

    public static function invalidSignature(): self
    {
        return new self(
            'The X-Recado-Signature header is missing or does not match the webhook body.',
            self::INVALID_SIGNATURE,
        );
    }

    public static function invalidPayload(string $reason): self
    {
        return new self('The webhook body is not a Recado webhook envelope: '.$reason, self::INVALID_PAYLOAD);
    }
}
