<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Webhooks\WebhookEvent;

/**
 * Subscription categories: a campaign scoped to a public tag
 * (`category_tag_id`) and the `contact.untagged` webhook a category opt-out
 * produces.
 */
final class CampaignCategoryTest extends TestCase
{
    public function test_the_category_is_read_back_and_null_when_absent(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->campaign(['category_tag_id' => 12])]),
            $this->jsonResponse(200, ['data' => $this->campaign()]),
        ], $history);

        $this->assertSame(12, $client->campaigns()->get(7)->categoryTagId);
        $this->assertNull($client->campaigns()->get(7)->categoryTagId);
    }

    public function test_update_passes_the_category_through_including_null(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->campaign(['category_tag_id' => 12])]),
            $this->jsonResponse(200, ['data' => $this->campaign()]),
        ], $history);

        $client->campaigns()->update(7, ['category_tag_id' => 12]);
        $client->campaigns()->update(7, ['category_tag_id' => null]);

        $this->assertSame(['category_tag_id' => 12], json_decode((string) $history[0]['request']->getBody(), true));
        $this->assertSame(['category_tag_id' => null], json_decode((string) $history[1]['request']->getBody(), true));
    }

    public function test_recipient_count_sends_the_category(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['recipients_total' => 4]]),
        ], $history);

        $this->assertSame(4, $client->campaigns()->recipientCount(lists: [3], categoryTagId: 12));

        parse_str($history[0]['request']->getUri()->getQuery(), $query);

        $this->assertSame('12', $query['category_tag_id']);
    }

    public function test_contact_untagged_is_in_the_webhook_catalog(): void
    {
        $this->assertContains('contact.untagged', WebhookEvent::values());
        $this->assertSame(WebhookEvent::ContactUntagged, WebhookEvent::from('contact.untagged'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function campaign(array $overrides = []): array
    {
        return array_merge([
            'id' => 7,
            'name' => 'Offers',
            'subject' => 'This week',
            'status' => 'draft',
            'recipients_total' => 0,
            'dispatched_total' => null,
            'sent_count' => 0,
            'failed_count' => 0,
            'scheduled_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_at' => '2026-09-01T10:00:00+00:00',
        ], $overrides);
    }
}
