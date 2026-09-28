<?php

declare(strict_types=1);

namespace Recado\Sdk\Webhooks;

use Recado\Sdk\Exception\WebhookVerificationException;

/**
 * Verifies the signature Recado puts on every outbound webhook delivery.
 *
 * Each delivery is a `POST` whose `X-Recado-Signature` header is the hex
 * HMAC-SHA256 of the EXACT raw request body, keyed with the endpoint's signing
 * secret (the `whsec_…` value `webhooks()->create()` returns once). Verify
 * against the raw bytes you received — never against a re-encoded array, whose
 * key order or escaping may differ.
 *
 * ```php
 * $payload = WebhookPayload::constructEvent(
 *     file_get_contents('php://input'),
 *     $_SERVER['HTTP_X_RECADO_SIGNATURE'] ?? null,
 *     getenv('RECADO_WEBHOOK_SECRET'),
 * );
 * ```
 *
 * `X-Recado-Delivery` (a uuid, stable across the retries of one delivery) is
 * the idempotency key to deduplicate on; `X-Recado-Event` repeats the event.
 */
final class WebhookSignature
{
    public const string SIGNATURE_HEADER = 'X-Recado-Signature';

    public const string EVENT_HEADER = 'X-Recado-Event';

    public const string DELIVERY_HEADER = 'X-Recado-Delivery';

    /**
     * The signature Recado computes for this body and secret.
     */
    public static function compute(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Whether `$signature` is the valid signature of `$payload` (constant-time).
     * A missing/empty signature or an empty secret is never valid.
     */
    public static function isValid(string $payload, ?string $signature, string $secret): bool
    {
        if ($signature === null || $secret === '') {
            return false;
        }

        $signature = strtolower(trim($signature));

        if ($signature === '') {
            return false;
        }

        return hash_equals(self::compute($payload, $secret), $signature);
    }

    /**
     * Verify the signature or throw.
     *
     * @throws WebhookVerificationException `invalid_webhook_signature`
     */
    public static function verify(string $payload, ?string $signature, string $secret): void
    {
        if (! self::isValid($payload, $signature, $secret)) {
            throw WebhookVerificationException::invalidSignature();
        }
    }
}
