<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\NotificationTemplate;
use Recado\Sdk\Dto\NotificationTemplateVariant;
use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Notification templates resource: CRUD plus per-locale variants — the
 * notification sibling of {@see TemplatesResource}.
 *
 * A notification template is addressed by SLUG (unique per project), which is
 * the identifier `notifications()->send()` accepts as `template`. Deleting one
 * is safe for notifications already queued (they snapshot their resolved
 * content), but every later send naming that slug fails.
 */
final readonly class NotificationTemplatesResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http) {}

    /**
     * List notification templates (GET /notification-templates) — compact form
     * (slug, name, title, dates).
     *
     * @param  array<string, mixed>  $query  per_page, page.
     * @return Paginated<NotificationTemplate>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('notification-templates', ['query' => $query]);

        return Paginated::fromArray($response, NotificationTemplate::fromArray(...));
    }

    /**
     * Lazily iterate every notification template across all pages
     * (GET /notification-templates).
     *
     * @param  array<string, mixed>  $query  per_page (page is managed automatically).
     * @return \Generator<int, NotificationTemplate>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * Fetch a single template's full form, variants included
     * (GET /notification-templates/{slug}).
     */
    public function get(string $slug): NotificationTemplate
    {
        $response = $this->http->get('notification-templates/'.rawurlencode($slug));

        return NotificationTemplate::fromArray($response['data'] ?? []);
    }

    /**
     * Create a notification template (POST /notification-templates).
     *
     * `action_url` is where tapping the notification leads: an absolute http(s)
     * URL or a custom-scheme deep link (`myapp://orders/42`), never a
     * script-executing scheme (the bell-feed widget renders it as a link on
     * your own site, so those would be stored XSS). `icon` must be an absolute
     * http(s) URL — it is fetched as an image.
     *
     * @param  array<string, mixed>  $payload  name, slug, title (≤200),
     *                                         body (≤2000), optional action_url,
     *                                         icon.
     */
    public function create(array $payload): NotificationTemplate
    {
        $response = $this->http->post('notification-templates', ['json' => $payload]);

        return NotificationTemplate::fromArray($response['data'] ?? []);
    }

    /**
     * Partially update a template (PATCH /notification-templates/{slug}).
     *
     * Send `null` to clear `action_url`/`icon`. Changing the slug re-checks
     * per-project uniqueness.
     *
     * @param  array<string, mixed>  $payload  Any of name, slug, title, body,
     *                                         action_url, icon.
     */
    public function update(string $slug, array $payload): NotificationTemplate
    {
        $response = $this->http->patch(
            'notification-templates/'.rawurlencode($slug),
            ['json' => $payload],
        );

        return NotificationTemplate::fromArray($response['data'] ?? []);
    }

    /**
     * Delete a template and its variants
     * (DELETE /notification-templates/{slug}).
     */
    public function delete(string $slug): void
    {
        $this->http->delete('notification-templates/'.rawurlencode($slug));
    }

    /**
     * Create or replace a per-locale variant
     * (PUT /notification-templates/{slug}/variants/{locale}).
     *
     * A FULL replace, not a merge: an omitted optional field is RESET, so a
     * variant saved without `action_url` gives recipients of that locale no tap
     * target rather than the base template's one. The locale tag is validated
     * and normalized server-side (`ES-mx` → `es-MX`).
     *
     * @param  array<string, mixed>  $payload  title, body, optional action_url, icon.
     */
    public function putVariant(string $slug, string $locale, array $payload): NotificationTemplateVariant
    {
        $response = $this->http->put(
            'notification-templates/'.rawurlencode($slug).'/variants/'.rawurlencode($locale),
            ['json' => $payload],
        );

        return NotificationTemplateVariant::fromArray($response['data'] ?? []);
    }

    /**
     * Delete a per-locale variant
     * (DELETE /notification-templates/{slug}/variants/{locale}).
     */
    public function deleteVariant(string $slug, string $locale): void
    {
        $this->http->delete(
            'notification-templates/'.rawurlencode($slug).'/variants/'.rawurlencode($locale),
        );
    }
}
