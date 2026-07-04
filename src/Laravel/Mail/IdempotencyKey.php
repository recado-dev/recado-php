<?php

declare(strict_types=1);

namespace Mailer\Sdk\Laravel\Mail;

use Illuminate\Support\Str;

/**
 * Resolves the idempotency key for a transactional send. An explicit override
 * always wins (the X-Mailer-Idempotency-Key header on the transport, or
 * {@see MailerMessage::idempotencyKey()} on the notification channel);
 * otherwise the configured strategy decides:
 *
 *   - `content` (default): a deterministic hash of the canonical payload, so a
 *     requeued job never duplicates the send.
 *   - `random`: a fresh UUID per call (no dedup).
 *   - `off`: no key.
 */
final class IdempotencyKey
{
    /**
     * @param array<string, mixed> $payload    The /send content payload. Its `to`
     *                                          value scopes the key: a scalar
     *                                          recipient yields a per-recipient
     *                                          key (single send / channel path); a
     *                                          sorted recipient list yields one key
     *                                          shared across a batch. Content
     *                                          without any `to` would collide
     *                                          across recipients — always pass it.
     * @param array<string, mixed> $mailConfig The `mailer-sdk.mail` config block.
     */
    public static function compute(array $payload, array $mailConfig, ?string $override = null): ?string
    {
        if ($override !== null && $override !== '') {
            return $override;
        }

        $strategy = (string) ($mailConfig['idempotency'] ?? 'content');

        return match ($strategy) {
            'random' => Str::uuid()->toString(),
            'off' => null,
            default => self::contentKey($payload),
        };
    }

    /**
     * Deterministic key derived from the canonical content, so a queue retry of
     * the same job never duplicates the send.
     *
     * @param array<string, mixed> $payload
     */
    private static function contentKey(array $payload): string
    {
        $canonical = [
            'subject' => $payload['subject'] ?? null,
            'body' => $payload['body'] ?? null,
            'text' => $payload['text'] ?? null,
            'template' => $payload['template'] ?? null,
            'variables' => $payload['variables'] ?? null,
            'to' => $payload['to'] ?? null,
        ];

        ksort($canonical);

        $hash = hash('sha256', (string) json_encode($canonical));

        // Prefix + truncate well under the API's 255-char idempotency key limit.
        return 'txn_'.substr($hash, 0, 60);
    }

    private function __construct()
    {
    }
}
