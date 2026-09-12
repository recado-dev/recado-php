<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\WebhookDelivery;
use Recado\Sdk\Exception\NotFoundException;

/**
 * The operational half of the webhooks resource: toggle, ping and the delivery
 * log — the three calls that recover and diagnose a failing endpoint.
 */
final class WebhookOperationsTest extends TestCase
{
    public function test_toggle_puts_the_flag_and_returns_the_recovered_endpoint(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'id' => 8,
                'url' => 'https://hooks.example.com/recado',
                'events' => ['contact.subscribed'],
                'enabled' => true,
                'consecutive_failures' => 0,
                'disabled_at' => null,
                'created_at' => '2026-09-01T10:00:00+00:00',
            ]]),
        ], $history);

        $endpoint = $client->webhooks()->toggle(8, true);

        // Re-enabling an auto-disabled endpoint is what resets the counter and
        // clears disabled_at — that is the whole point of this call.
        $this->assertTrue($endpoint->enabled);
        $this->assertSame(0, $endpoint->consecutiveFailures);
        $this->assertNull($endpoint->disabledAt);
        $this->assertNull($endpoint->secret);

        $request = $history[0]['request'];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/api/v1/webhooks/8/toggle', $request->getUri()->getPath());
        $this->assertSame(['enabled' => true], json_decode((string) $request->getBody(), true));
    }

    public function test_ping_reports_only_that_the_delivery_was_queued(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['queued' => true, 'webhook_id' => 8, 'event' => 'ping']]),
        ], $history);

        $this->assertTrue($client->webhooks()->ping(8));

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/webhooks/8/ping', $request->getUri()->getPath());
    }

    public function test_deliveries_is_paginated_and_keeps_a_missing_status_null(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [
                    ['id' => 412, 'event' => 'ping', 'status' => 200, 'success' => true, 'attempt' => 1, 'created_at' => '2026-09-11T09:12:00+00:00'],
                    ['id' => 411, 'event' => 'campaign.sent', 'status' => null, 'success' => false, 'attempt' => 3, 'created_at' => '2026-09-11T08:41:07+00:00'],
                ],
                'meta' => ['current_page' => 1, 'per_page' => 2, 'total' => 2],
                'links' => ['next' => null],
            ]),
        ], $history);

        $page = $client->webhooks()->deliveries(8, ['per_page' => 2]);

        $this->assertContainsOnlyInstancesOf(WebhookDelivery::class, $page->data);
        $this->assertSame(200, $page->data[0]->status);
        $this->assertTrue($page->data[0]->success);
        // No response at all (timeout, connection error) is reported as a null
        // status, not as a 0.
        $this->assertNull($page->data[1]->status);
        $this->assertSame(3, $page->data[1]->attempt);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/webhooks/8/deliveries', $request->getUri()->getPath());
        $this->assertStringContainsString('per_page=2', $request->getUri()->getQuery());
    }

    public function test_deliveries_cursor_walks_every_page(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [['id' => 412, 'event' => 'ping', 'status' => 200, 'success' => true, 'attempt' => 1]],
                'meta' => ['current_page' => 1, 'last_page' => 2, 'per_page' => 1, 'total' => 2],
                'links' => ['next' => 'https://recado.example.com/api/v1/webhooks/8/deliveries?page=2'],
            ]),
            $this->jsonResponse(200, [
                'data' => [['id' => 411, 'event' => 'ping', 'status' => 500, 'success' => false, 'attempt' => 2]],
                'meta' => ['current_page' => 2, 'last_page' => 2, 'per_page' => 1, 'total' => 2],
                'links' => ['next' => null],
            ]),
        ], $history);

        $ids = [];

        foreach ($client->webhooks()->deliveriesCursor(8, ['per_page' => 1]) as $delivery) {
            $ids[] = $delivery->id;
        }

        $this->assertSame([412, 411], $ids);
        $this->assertCount(2, $history);
    }

    public function test_an_unknown_endpoint_is_a_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'Webhook not found.', 'code' => 'webhook_not_found']),
        ], $history);

        try {
            $client->webhooks()->ping(999);
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('webhook_not_found', $e->getErrorCode());
        }
    }
}
