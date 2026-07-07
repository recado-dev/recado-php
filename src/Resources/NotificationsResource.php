<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

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
}
