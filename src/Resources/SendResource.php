<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\BatchResult;
use Recado\Sdk\Dto\SentMessage;
use Recado\Sdk\Http\HttpClient;

/**
 * The Send resource: transactional sends, batch sends, event tracking and
 * contact subscription.
 */
final readonly class SendResource
{
    public function __construct(private HttpClient $http)
    {
    }

    /**
     * Send a single transactional email (POST /send).
     *
     * The payload is passed through as-is, so every /send field works,
     * including `attachments`: an array (max 10) of `{filename, content_type,
     * content}` objects where `content` is standard base64 (limits: 10 MB
     * decoded per file and per send — the latter rejected with a 422 and code
     * `attachments_too_large`; executable filename extensions are refused).
     *
     * @param array<string, mixed> $payload `to` plus either `template` or
     *                                       `subject`+`body`, optional `text`,
     *                                       `variables`, `attachments`.
     */
    public function email(array $payload, ?string $idempotencyKey = null): SentMessage
    {
        $options = ['json' => $payload];

        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        $response = $this->http->post('send', $options);

        return SentMessage::fromArray($response['data'] ?? []);
    }

    /**
     * Send a batch of transactional emails (POST /send/batch).
     *
     * The batch endpoint rejects `attachments` on any message (422) — the
     * field is single-send only; use {@see email()} per recipient instead
     * (the Laravel mail transport does that fan-out automatically).
     *
     * @param array<int, array<string, mixed>> $messages 1-100 message payloads.
     */
    public function batch(array $messages, ?string $idempotencyKey = null): BatchResult
    {
        $options = ['json' => ['messages' => array_values($messages)]];

        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        $response = $this->http->post('send/batch', $options);

        return BatchResult::fromArray($response['data'] ?? []);
    }

    /**
     * Record an event occurrence for a contact (POST /track).
     *
     * @param array<string, mixed> $data Optional event payload.
     *
     * @return array<string, mixed> The `data` block: id, event, email.
     */
    public function track(string $event, string $email, array $data = []): array
    {
        $payload = ['event' => $event, 'email' => $email];

        if ($data !== []) {
            $payload['data'] = $data;
        }

        $response = $this->http->post('track', ['json' => $payload]);

        return $response['data'] ?? [];
    }

    /**
     * Subscribe a contact (POST /contacts/subscribe).
     *
     * @param array<string, mixed> $payload `email` plus optional first_name,
     *                                       last_name, locale, attributes,
     *                                       lists, tags.
     *
     * @return array<string, mixed> The `data` block: id, email, status.
     */
    public function subscribe(array $payload): array
    {
        $response = $this->http->post('contacts/subscribe', ['json' => $payload]);

        return $response['data'] ?? [];
    }
}
