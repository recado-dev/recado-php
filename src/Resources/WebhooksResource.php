<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\WebhookEndpoint;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Webhooks\WebhookEvent;

/**
 * The Webhooks resource: manage the project's outbound webhook endpoints.
 *
 * The signing secret is returned exactly once, by create() — store it right
 * there; no other endpoint ever includes it again.
 */
final readonly class WebhooksResource
{
    public function __construct(private HttpClient $http) {}

    /**
     * All endpoints of the project, newest first (GET /webhooks). This
     * endpoint is a flat array, not paginated, and never carries the secret.
     *
     * @return array<int, WebhookEndpoint>
     */
    public function list(): array
    {
        $response = $this->http->get('webhooks');

        $endpoints = [];

        foreach ($response['data'] ?? [] as $endpoint) {
            if (is_array($endpoint)) {
                $endpoints[] = WebhookEndpoint::fromArray($endpoint);
            }
        }

        return $endpoints;
    }

    /**
     * Create an endpoint (POST /webhooks).
     *
     * The returned DTO is the ONLY place the signing `secret` ever appears.
     * The URL must be a public https address (private/internal hosts are
     * rejected with a `422` to prevent SSRF).
     *
     * @param  array<int, WebhookEvent|string>  $events  At least one event of the
     *                                                   catalog (`ping` is the test
     *                                                   button and is not subscribable).
     */
    public function create(string $url, array $events, bool $enabled = true): WebhookEndpoint
    {
        $response = $this->http->post('webhooks', [
            'json' => [
                'url' => $url,
                'events' => self::normalizeEvents($events),
                'enabled' => $enabled,
            ],
        ]);

        return WebhookEndpoint::fromArray($response['data'] ?? []);
    }

    /**
     * Partially update an endpoint (PATCH /webhooks/{id}).
     *
     * Re-enabling a disabled endpoint resets `consecutive_failures` and clears
     * `disabled_at`. `events` may be given as strings or WebhookEvent cases.
     *
     * @param  array<string, mixed>  $payload  Any of `url`, `events`, `enabled`.
     */
    public function update(int $id, array $payload): WebhookEndpoint
    {
        if (isset($payload['events']) && is_array($payload['events'])) {
            $payload['events'] = self::normalizeEvents($payload['events']);
        }

        $response = $this->http->patch('webhooks/'.$id, ['json' => $payload]);

        return WebhookEndpoint::fromArray($response['data'] ?? []);
    }

    /**
     * Delete an endpoint and its delivery log (DELETE /webhooks/{id}).
     */
    public function delete(int $id): void
    {
        $this->http->delete('webhooks/'.$id);
    }

    /**
     * Accept both WebhookEvent cases and raw strings, so a new platform event
     * is usable before the SDK enum ships it.
     *
     * @param  array<int, mixed>  $events
     * @return array<int, string>
     */
    private static function normalizeEvents(array $events): array
    {
        $normalized = [];

        foreach ($events as $event) {
            if ($event instanceof WebhookEvent) {
                $normalized[] = $event->value;

                continue;
            }

            if (is_scalar($event)) {
                $normalized[] = (string) $event;
            }
        }

        return $normalized;
    }
}
