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
    public function __construct(private HttpClient $http) {}

    /**
     * Register a push device token for a contact (POST /push/tokens).
     *
     * The contact is upserted with transactional semantics. Registering a
     * token already owned by another contact in the project (in the same
     * app) moves it; a contact is capped at 20 devices per app (the oldest
     * of that app is evicted past the cap).
     *
     * This endpoint registers FCM registration tokens only (on iOS too — a
     * raw APNs device token is not accepted). Web push uses a separate VAPID
     * subscription endpoint, so `web` is not a valid platform here — passing
     * it yields a 422.
     *
     * `$app` is the key of the push app the device belongs to, for projects
     * that deliver to several apps. Omitted, the device joins the project's
     * default app. An unknown or disabled key throws a ValidationException
     * with code `push_app_not_found`.
     *
     * @param  string  $platform  One of `ios`, `android`.
     * @param  string|null  $app  Key of a push app; null = the default app.
     */
    public function register(string $email, string $token, string $platform, ?string $app = null): PushTokenResult
    {
        $payload = ['email' => $email, 'token' => $token, 'platform' => $platform];

        if ($app !== null) {
            $payload['app'] = $app;
        }

        $response = $this->http->post('push/tokens', ['json' => $payload]);

        return PushTokenResult::fromArray($response['data'] ?? []);
    }

    /**
     * Remove a push device token from a contact (DELETE /push/tokens).
     *
     * `removed` is false when the contact had no such token. An unknown
     * contact raises a NotFoundException (`contact_not_found`).
     *
     * Without `$app` the token is removed wherever the contact holds it;
     * with `$app` (a push app key) only from that app.
     */
    public function remove(string $email, string $token, ?string $app = null): PushTokenResult
    {
        $payload = ['email' => $email, 'token' => $token];

        if ($app !== null) {
            $payload['app'] = $app;
        }

        $response = $this->http->delete('push/tokens', ['json' => $payload]);

        return PushTokenResult::fromArray($response['data'] ?? []);
    }
}
