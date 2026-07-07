<?php

declare(strict_types=1);

namespace Recado\Sdk\Laravel\Mail;

/**
 * Custom MIME header names understood by the {@see RecadoTransport}.
 *
 * A Mailable can set these via the Symfony message to drive template-based
 * sends, per-recipient variables and explicit idempotency keys, e.g.:
 *
 *   $message->getHeaders()->addTextHeader(RecadoHeaders::TEMPLATE, 'welcome');
 *
 * The transport reads and strips them before talking to the API; they never
 * reach the wire as SMTP headers (the platform /send endpoint takes JSON).
 */
final class RecadoHeaders
{
    /**
     * Slug of a stored template to render instead of the inline subject/body.
     */
    public const TEMPLATE = 'X-Recado-Template';

    /**
     * JSON-encoded object of template/inline placeholder variables.
     */
    public const VARIABLES = 'X-Recado-Variables';

    /**
     * Explicit idempotency key, overriding the transport's automatic strategy.
     */
    public const IDEMPOTENCY_KEY = 'X-Recado-Idempotency-Key';

    private function __construct()
    {
    }
}
