<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\NotificationAnalytics;
use Recado\Sdk\Dto\NotificationBatchResult;
use Recado\Sdk\Dto\NotificationResult;
use Recado\Sdk\Exception\RecadoException;
use Recado\Sdk\Exception\ValidationException;
use Recado\Sdk\Http\HttpClient;

/**
 * The Notifications resource: multichannel (in-app + push) notification sends
 * (POST /notifications).
 */
final readonly class NotificationsResource
{
    public function __construct(private HttpClient $http) {}

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
     * Content is either an inline `title` + `body` pair or a `template`
     * slug (a notification template managed in the dashboard) — mutually
     * exclusive. Template mode resolves the template's locale variants per
     * recipient and snapshots the content at queue time; the per-send
     * `action_url`/`icon` override the template defaults. An unknown slug
     * throws a {@see ValidationException} with code `template_not_found`.
     *
     * `app` (optional, requires `push` among `channels`) is the key of the
     * push app the push goes to, for projects that deliver to several apps:
     * with it only that app's devices are reached; without it the default
     * app and every non-restricted app are — never a restricted one. An
     * unknown or disabled key throws a {@see ValidationException} with code
     * `push_app_not_found`.
     *
     * `push` (optional, requires `push` among `channels`) carries the
     * push-only extras a native app needs; it is never part of the in-app
     * notification of the same send. Every key is optional:
     *
     *  - `sound` (string, ≤ 100): a sound file bundled with the app;
     *  - `badge` (int, 0..99999): the app icon badge, `0` clears it;
     *  - `category` (string, ≤ 64): the id of a category the app registered;
     *  - `thread_id` (string, ≤ 64): groups notifications together;
     *  - `interruption_level`: `passive`, `active` or `time-sensitive`
     *    (`critical` is not supported);
     *  - `android_channel_id` (string, ≤ 100): the Android notification channel;
     *  - `data` (array<string, scalar>): custom values for the app — a flat
     *    map, at most 10 keys; string values may use `{{ placeholders }}`.
     *    Reserved keys (`aps`, `message_uuid`, `action_url`, `icon`, `title`,
     *    `body`, `from`, `message_type`, `notification`, `collapse_key`,
     *    anything starting with `google` or `gcm`) are refused;
     *  - `silent` (bool): a background push with no alert. `title`/`body`
     *    become optional, `channels` must be exactly `['push']` (pass it
     *    explicitly: this method defaults to in-app), and `sound`, `badge`,
     *    `category` and `interruption_level` are not allowed with it. A
     *    silent push counts toward the push quota, is never marketing and is
     *    left out of the open and click rates. It reaches native apps only
     *    (APNs and FCM), never a browser. Apple throttles background
     *    pushes to a few per hour: a hint to refresh, not a live channel.
     *
     * An invalid `push` object throws a {@see ValidationException}. So does
     * a send whose push payload would exceed 3500 bytes (title, body,
     * action_url, icon and the `push` object as the push services receive
     * them, counted in bytes), with the code `push_payload_too_large`; a
     * send without a `push` object is never measured.
     *
     * @param  array<string, mixed>  $payload  `to`, then `title` + `body` or a
     *                                         `template` slug, plus optional
     *                                         `channels` (defaults to `['in_app']`),
     *                                         `action_url`, `icon`, `variables`,
     *                                         `app`, `push`.
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
     * Each item carries the same fields as {@see send()} (`to`, then
     * `title` + `body` or a `template` slug, optional `channels` —
     * defaulting to `['in_app']` —, `action_url`, `icon`, `variables`,
     * `app` and the `push` extras, silent pushes included;
     * an item's unknown template slug is a per-channel
     * `failed_precondition`/`template_not_found` outcome, never an
     * exception — and so is an unknown `app` key, reported as
     * `push_app_not_found`); 1-100 items per request, rate
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
     * {@see RecadoException} carrying status `409` and
     * code `idempotency_conflict` — retry shortly after.
     *
     * @param  array<int, array<string, mixed>>  $messages  1-100 notification payloads.
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

    /**
     * The rolling 30-day push / in-app aggregation
     * (GET /notifications/analytics) — the answer to "how is the push channel
     * doing?", which the messages endpoint cannot give you because direct API
     * notifications are N loose messages with no campaign to hang stats on.
     *
     * Unlike delivery health this DOES work inside a sandbox: intercepted sends
     * are recorded, so it is how you read back a test run.
     */
    public function analytics(): NotificationAnalytics
    {
        $response = $this->http->get('notifications/analytics');

        return NotificationAnalytics::fromArray($response['data'] ?? []);
    }
}
