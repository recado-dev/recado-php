<?php

declare(strict_types=1);

namespace Mailer\Sdk\Laravel\Mail;

/**
 * Custom MIME header names understood by the {@see MailerTransport}.
 *
 * A Mailable can set these via the Symfony message to drive template-based
 * sends, per-recipient variables and explicit idempotency keys, e.g.:
 *
 *   $message->getHeaders()->addTextHeader(MailerHeaders::TEMPLATE, 'welcome');
 *
 * The transport reads and strips them before talking to the API; they never
 * reach the wire as SMTP headers (the platform /send endpoint takes JSON).
 */
final class MailerHeaders
{
    /**
     * Slug of a stored template to render instead of the inline subject/body.
     */
    public const TEMPLATE = 'X-Mailer-Template';

    /**
     * JSON-encoded object of template/inline placeholder variables.
     */
    public const VARIABLES = 'X-Mailer-Variables';

    /**
     * Explicit idempotency key, overriding the transport's automatic strategy.
     */
    public const IDEMPOTENCY_KEY = 'X-Mailer-Idempotency-Key';

    private function __construct()
    {
    }
}
