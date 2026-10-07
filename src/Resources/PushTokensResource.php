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
     * The token format follows the transport of the push app: the default
     * app and FCM apps take the FCM registration token (on iOS too), a
     * direct-APNs app takes the raw APNs device token as hex (64 to 200 hex
     * characters, any case). Web push uses a separate VAPID subscription
     * endpoint, so `web` is not a valid platform here — passing it yields a
     * 422.
     *
     * `$app` is the key of the push app the device belongs to, for projects
     * that deliver to several apps. Omitted, the device joins the project's
     * default app. An unknown or disabled key throws a ValidationException
     * with code `push_app_not_found`.
     *
     * `$environment` is the APNs environment of the token, for a direct-APNs
     * app only: `sandbox` for a development build (run from Xcode),
     * `production` for TestFlight / App Store / notarized Mac builds.
     * Omitted, the server assumes `production`. It is ignored for FCM apps.
     * A token registered under the wrong environment is answered
     * `BadDeviceToken` by Apple and removed on its first send.
     *
     * @param  string  $platform  `ios` or `android` (default app, FCM apps); `ios` or `macos` (direct-APNs apps).
     * @param  string|null  $app  Key of a push app; null = the default app.
     * @param  string|null  $environment  `production` or `sandbox`; null = not sent (production).
     */
    public function register(
        string $email,
        string $token,
        string $platform,
        ?string $app = null,
        ?string $environment = null,
    ): PushTokenResult {
        $payload = ['email' => $email, 'token' => $token, 'platform' => $platform];

        if ($app !== null) {
            $payload['app'] = $app;
        }

        if ($environment !== null) {
            $payload['environment'] = $environment;
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
