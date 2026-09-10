<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\WebhookEndpoint;
use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\RateLimitException;
use Recado\Sdk\Exception\ValidationException;
use Recado\Sdk\Webhooks\WebhookEvent;

final class WebhooksTest extends TestCase
{
    public function test_list_parses_the_flat_array_and_never_carries_a_secret(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [$this->endpoint()]]),
        ], $history);

        $endpoints = $client->webhooks()->list();

        $this->assertCount(1, $endpoints);
        $this->assertContainsOnlyInstancesOf(WebhookEndpoint::class, $endpoints);
        $this->assertSame(8, $endpoints[0]->id);
        $this->assertSame(['contact.subscribed', 'campaign.sent'], $endpoints[0]->events);
        $this->assertTrue($endpoints[0]->enabled);
        $this->assertNull($endpoints[0]->secret);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/webhooks', $request->getUri()->getPath());
    }

    public function test_create_sends_enum_cases_as_strings_and_returns_the_one_time_secret(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->endpoint([
                'events' => ['campaign.started', 'campaign.failed'],
                'secret' => 'whsec_abc123',
            ])]),
        ], $history);

        $endpoint = $client->webhooks()->create('https://hooks.example.com/recado', [
            WebhookEvent::CampaignStarted,
            'campaign.failed',
        ]);

        $this->assertSame('whsec_abc123', $endpoint->secret);
        $this->assertSame(['campaign.started', 'campaign.failed'], $endpoint->events);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/webhooks', $request->getUri()->getPath());
        $this->assertSame([
            'url' => 'https://hooks.example.com/recado',
            'events' => ['campaign.started', 'campaign.failed'],
            'enabled' => true,
        ], json_decode((string) $request->getBody(), true));
    }

    public function test_the_event_catalog_covers_the_campaign_lifecycle(): void
    {
        $values = WebhookEvent::values();

        foreach ([
            'campaign.scheduled',
            'campaign.started',
            'campaign.sent',
            'campaign.failed',
            'campaign.cancelled',
        ] as $event) {
            $this->assertContains($event, $values);
        }

        $this->assertNotContains('ping', $values, 'ping is the test-button event and is not subscribable.');
    }

    public function test_update_normalizes_events_and_patches(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->endpoint([
                'url' => 'https://hooks.example.com/v2',
                'events' => ['message.delivered'],
            ])]),
        ], $history);

        $endpoint = $client->webhooks()->update(8, [
            'url' => 'https://hooks.example.com/v2',
            'events' => [WebhookEvent::MessageDelivered],
            'enabled' => true,
        ]);

        $this->assertSame(['message.delivered'], $endpoint->events);
        $this->assertNull($endpoint->secret, 'The secret is never returned again.');

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/api/v1/webhooks/8', $request->getUri()->getPath());
        $this->assertSame([
            'url' => 'https://hooks.example.com/v2',
            'events' => ['message.delivered'],
            'enabled' => true,
        ], json_decode((string) $request->getBody(), true));
    }

    public function test_delete_issues_a_delete(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(204, []),
        ], $history);

        $client->webhooks()->delete(8);

        $this->assertSame('DELETE', $history[0]['request']->getMethod());
        $this->assertSame('/api/v1/webhooks/8', $history[0]['request']->getUri()->getPath());
    }

    public function test_a_private_url_raises_a_validation_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The url field must be a public http(s) URL.',
                'errors' => ['url' => ['The url field must be a public http(s) URL.']],
            ]),
        ], $history);

        try {
            $client->webhooks()->create('http://localhost/hook', [WebhookEvent::CampaignSent]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('url', $e->errors());
        }
    }

    public function test_an_unknown_endpoint_raises_a_not_found_exception_with_its_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'Webhook not found.', 'code' => 'webhook_not_found']),
        ], $history);

        try {
            $client->webhooks()->delete(99);
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame('webhook_not_found', $e->getErrorCode());
        }
    }

    public function test_a_429_maps_to_the_rate_limit_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(429, ['message' => 'Too Many Requests'], ['Retry-After' => '5']),
        ], $history);

        try {
            $client->webhooks()->list();
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(5, $e->retryAfter());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function endpoint(array $overrides = []): array
    {
        return array_merge([
            'id' => 8,
            'url' => 'https://hooks.example.com/recado',
            'events' => ['contact.subscribed', 'campaign.sent'],
            'enabled' => true,
            'consecutive_failures' => 0,
            'disabled_at' => null,
            'created_at' => '2026-06-12T09:00:00+00:00',
        ], $overrides);
    }
}
