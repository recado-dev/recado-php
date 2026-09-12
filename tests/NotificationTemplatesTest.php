<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\NotificationTemplate;
use Recado\Sdk\Exception\NotFoundException;

/**
 * The notification-templates resource: CRUD plus the per-locale variants.
 */
final class NotificationTemplatesTest extends TestCase
{
    public function test_list_is_paginated_and_compact(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [[
                    'slug' => 'order-shipped',
                    'name' => 'Order shipped',
                    'title' => 'On its way',
                    'created_at' => '2026-09-11T10:00:00+00:00',
                    'updated_at' => '2026-09-11T10:00:00+00:00',
                ]],
                'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1],
                'links' => ['next' => null],
            ]),
        ], $history);

        $page = $client->notificationTemplates()->list();

        $this->assertContainsOnlyInstancesOf(NotificationTemplate::class, $page->data);
        $this->assertSame('order-shipped', $page->data[0]->slug);
        // The compact form carries no body and no variants.
        $this->assertNull($page->data[0]->body);
        $this->assertSame([], $page->data[0]->variants);

        $this->assertSame('/api/v1/notification-templates', $history[0]['request']->getUri()->getPath());
    }

    public function test_create_returns_the_full_template(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->template()]),
        ], $history);

        $template = $client->notificationTemplates()->create([
            'name' => 'Order shipped',
            'slug' => 'order-shipped',
            'title' => 'On its way, {{ contact.first_name }}',
            'body' => 'Your order left the warehouse.',
            'action_url' => 'myapp://orders/42',
        ]);

        $this->assertSame('Your order left the warehouse.', $template->body);
        // A custom-scheme deep link is the canonical push tap action and must
        // survive the round trip untouched.
        $this->assertSame('myapp://orders/42', $template->actionUrl);
        $this->assertNull($template->icon);
        $this->assertSame([], $template->variants);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/notification-templates', $request->getUri()->getPath());
    }

    public function test_get_maps_the_locale_variants(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->template([
                'variants' => [[
                    'locale' => 'es-MX',
                    'title' => 'En camino',
                    'body' => 'Tu pedido salió del almacén.',
                    'action_url' => null,
                    'icon' => null,
                    'created_at' => '2026-09-11T10:00:00+00:00',
                    'updated_at' => '2026-09-11T10:00:00+00:00',
                ]],
            ])]),
        ], $history);

        $template = $client->notificationTemplates()->get('order-shipped');

        $this->assertCount(1, $template->variants);
        $this->assertSame('es-MX', $template->variants[0]->locale);
        // A variant is a FULL alternative: its empty action_url wins over the
        // base one, so recipients of that locale get no tap target.
        $this->assertNull($template->variants[0]->actionUrl);
    }

    public function test_update_can_clear_the_optional_fields_with_null(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->template(['action_url' => null])]),
        ], $history);

        $template = $client->notificationTemplates()->update('order-shipped', ['action_url' => null]);

        $this->assertNull($template->actionUrl);

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/api/v1/notification-templates/order-shipped', $request->getUri()->getPath());
        $this->assertSame(['action_url' => null], json_decode((string) $request->getBody(), true));
    }

    public function test_put_variant_uses_put_and_url_encodes_the_locale(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => [
                'locale' => 'es-MX',
                'title' => 'En camino',
                'body' => 'Tu pedido salió del almacén.',
                'action_url' => null,
                'icon' => null,
            ]]),
        ], $history);

        $variant = $client->notificationTemplates()->putVariant('order-shipped', 'ES-mx', [
            'title' => 'En camino',
            'body' => 'Tu pedido salió del almacén.',
        ]);

        // The server normalizes the tag; the SDK sends what it was given.
        $this->assertSame('es-MX', $variant->locale);

        $request = $history[0]['request'];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/api/v1/notification-templates/order-shipped/variants/ES-mx', $request->getUri()->getPath());
    }

    public function test_delete_variant_and_delete_template_issue_deletes(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(204, []),
            $this->jsonResponse(204, []),
        ], $history);

        $client->notificationTemplates()->deleteVariant('order-shipped', 'es-MX');
        $client->notificationTemplates()->delete('order-shipped');

        $this->assertSame('DELETE', $history[0]['request']->getMethod());
        $this->assertSame(
            '/api/v1/notification-templates/order-shipped/variants/es-MX',
            $history[0]['request']->getUri()->getPath(),
        );
        $this->assertSame('/api/v1/notification-templates/order-shipped', $history[1]['request']->getUri()->getPath());
    }

    public function test_an_unknown_slug_is_a_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, [
                'message' => 'Notification template not found.',
                'code' => 'notification_template_not_found',
            ]),
        ], $history);

        try {
            $client->notificationTemplates()->get('nope');
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('notification_template_not_found', $e->getErrorCode());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function template(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'order-shipped',
            'name' => 'Order shipped',
            'title' => 'On its way, {{ contact.first_name }}',
            'body' => 'Your order left the warehouse.',
            'action_url' => 'myapp://orders/42',
            'icon' => null,
            'variants' => [],
            'created_at' => '2026-09-11T10:00:00+00:00',
            'updated_at' => '2026-09-11T10:00:00+00:00',
        ], $overrides);
    }
}
