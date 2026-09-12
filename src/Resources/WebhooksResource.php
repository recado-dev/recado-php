<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Dto\WebhookDelivery;
use Recado\Sdk\Dto\WebhookEndpoint;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;
use Recado\Sdk\Webhooks\WebhookEvent;

/**
 * The Webhooks resource: manage the project's outbound webhook endpoints.
 *
 * The signing secret is returned exactly once, by create() — store it right
 * there; no other endpoint ever includes it again.
 */
final readonly class WebhooksResource
{
    use PaginatesResults;

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
     * Enable or disable an endpoint without touching its url/events
     * (PUT /webhooks/{id}/toggle).
     *
     * Enabling a DISABLED endpoint also resets `consecutive_failures` and
     * clears `disabled_at` — this is how you recover one that auto-disabled
     * after 10 consecutive failed deliveries.
     */
    public function toggle(int $id, bool $enabled): WebhookEndpoint
    {
        $response = $this->http->put('webhooks/'.$id.'/toggle', [
            'json' => ['enabled' => $enabled],
        ]);

        return WebhookEndpoint::fromArray($response['data'] ?? []);
    }

    /**
     * Queue a test `ping` delivery (POST /webhooks/{id}/ping) — the same
     * signed POST a real event produces.
     *
     * It answers `202`, which means QUEUED, not delivered: read the outcome
     * back from `deliveries()`. Pings reach disabled endpoints too, so this
     * stays usable while debugging one.
     *
     * @return bool The `queued` flag of the response.
     */
    public function ping(int $id): bool
    {
        $response = $this->http->post('webhooks/'.$id.'/ping');

        return (bool) ($response['data']['queued'] ?? false);
    }

    /**
     * The endpoint's delivery attempts, newest first
     * (GET /webhooks/{id}/deliveries).
     *
     * Only the last 100 attempts per endpoint are kept and the signed payload
     * is never stored, so this is a diagnosis surface, not an audit log.
     *
     * @param  array<string, mixed>  $query  per_page (max 100), page.
     * @return Paginated<WebhookDelivery>
     */
    public function deliveries(int $id, array $query = []): Paginated
    {
        $response = $this->http->get('webhooks/'.$id.'/deliveries', ['query' => $query]);

        return Paginated::fromArray($response, WebhookDelivery::fromArray(...));
    }

    /**
     * Lazily iterate every delivery attempt across all pages
     * (GET /webhooks/{id}/deliveries).
     *
     * @param  array<string, mixed>  $query  per_page (page is managed automatically).
     * @return \Generator<int, WebhookDelivery>
     */
    public function deliveriesCursor(int $id, array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->deliveries($id, array_merge($query, ['page' => $page])),
        );
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
