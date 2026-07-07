<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\PushTokenResult;
use Recado\Sdk\Http\HttpClient;

/**
 * The Push tokens resource: register and remove device tokens used to deliver
 * push notifications to a contact.
 */
final readonly class PushTokensResource
{
    public function __construct(private HttpClient $http)
    {
    }

    /**
     * Register a push device token for a contact (POST /push/tokens).
     *
     * The contact is upserted with transactional semantics. Registering a
     * token already owned by another contact in the project moves it; a
     * contact is capped at 20 devices (the oldest is evicted past the cap).
     *
     * This endpoint registers native FCM device tokens only. Web push uses a
     * separate VAPID subscription endpoint, so `web` is not a valid platform
     * here — passing it yields a 422.
     *
     * @param string $platform One of `ios`, `android`.
     */
    public function register(string $email, string $token, string $platform): PushTokenResult
    {
        $response = $this->http->post('push/tokens', [
            'json' => ['email' => $email, 'token' => $token, 'platform' => $platform],
        ]);

        return PushTokenResult::fromArray($response['data'] ?? []);
    }

    /**
     * Remove a push device token from a contact (DELETE /push/tokens).
     *
     * `removed` is false when the contact had no such token. An unknown
     * contact raises a NotFoundException (`contact_not_found`).
     */
    public function remove(string $email, string $token): PushTokenResult
    {
        $response = $this->http->delete('push/tokens', [
            'json' => ['email' => $email, 'token' => $token],
        ]);

        return PushTokenResult::fromArray($response['data'] ?? []);
    }
}
