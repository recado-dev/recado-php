<?php

declare(strict_types=1);

namespace Mailer\Sdk\Laravel\Events;

/**
 * Dispatched when the platform rejects a recipient because the address is on
 * the suppression list (bounced/complained or globally suppressed). This is
 * NOT a transport failure: the send completes normally and the rest of the
 * recipients (in a batch) are delivered. Listen for it to clean up your own
 * mailing lists.
 */
final class MessageSuppressed
{
    /**
     * @param string                    $recipient The suppressed email address.
     * @param string|null               $reason    The API error code/reason, e.g. "recipient_suppressed".
     * @param array<string, mixed>|null $body      The raw decoded API response (single send) when available.
     */
    public function __construct(
        public readonly string $recipient,
        public readonly ?string $reason = null,
        public readonly ?array $body = null,
    ) {
    }
}
