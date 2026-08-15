<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\NotificationBatchResult;
use Recado\Sdk\Dto\NotificationResult;
use Recado\Sdk\Exception\ValidationException;
use Recado\Sdk\Http\HttpClient;

/**
 * The Notifications resource: multichannel (in-app + push) notification sends
 * (POST /notifications).
 */
final readonly class NotificationsResource
{
    public function __construct(private HttpClient $http)
    {
    }

    /**
     * Send a notification to a contact (POST /notifications).
     *
     * The SDK always requests the per-channel envelope: when the caller does
     * not specify `channels`, `in_app` is injected so the response is always
     * the `{data: {messages: [...]}}` shape parsed here.
     *
     * Per-channel failures are DATA, not exceptions: the API returns the same
     * envelope with a 422 status when NO channel could be queued, and this
     * method hydrates it into a {@see NotificationResult} instead of throwing.
     * A real validation 422 (an `errors` map, no `data`) still throws
     * {@see ValidationException}.
     *
     * @param array<string, mixed> $payload `to`, `title`, `body` plus optional
     *                                       `channels` (defaults to `['in_app']`),
     *                                       `action_url`, `icon`, `variables`.
     */
    public function send(array $payload): NotificationResult
    {
        if (! array_key_exists('channels', $payload)) {
            $payload['channels'] = ['in_app'];
        }

        try {
            $response = $this->http->post('notifications', ['json' => $payload]);
        } catch (ValidationException $exception) {
            $body = $exception->getBody();

            if (is_array($body) && isset($body['data']['messages']) && is_array($body['data']['messages'])) {
                return NotificationResult::fromArray($body['data']);
            }

            throw $exception;
        }

        return NotificationResult::fromArray($response['data'] ?? []);
    }

    /**
     * Send a batch of notifications (POST /notifications/batch).
     *
     * Each item carries the same fields as {@see send()} (`to`, `title`,
     * `body`, optional `channels` — defaulting to `['in_app']` —,
     * `action_url`, `icon`, `variables`); 1-100 items per request, rate
     * limited at 10 requests/min per token. A single malformed item rejects
     * the WHOLE request with a {@see ValidationException}; runtime outcomes
     * are per item and per channel and never abort the batch, so unlike
     * {@see send()} the endpoint always answers `202` — inspect `queued` /
     * `failed` and the per-channel results.
     *
     * Passing an `$idempotencyKey` replays the recorded response for 24
     * hours instead of queueing a second batch (its own key namespace: a
     * `/send/batch` key with the same string is unrelated). A retry arriving
     * while the first request is still in flight throws a
     * {@see \Recado\Sdk\Exception\RecadoException} carrying status `409` and
     * code `idempotency_conflict` — retry shortly after.
     *
     * @param array<int, array<string, mixed>> $messages 1-100 notification payloads.
     */
    public function batch(array $messages, ?string $idempotencyKey = null): NotificationBatchResult
    {
        $options = ['json' => ['messages' => array_values($messages)]];

        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        $response = $this->http->post('notifications/batch', $options);

        return NotificationBatchResult::fromArray($response['data'] ?? []);
    }
}
