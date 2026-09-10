<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Campaign;
use Recado\Sdk\Dto\CampaignPreview;
use Recado\Sdk\Dto\CampaignReadiness;
use Recado\Sdk\Dto\CampaignStats;
use Recado\Sdk\Exception\CampaignSendNotConfirmedException;
use Recado\Sdk\Exception\RateLimitException;
use Recado\Sdk\Exception\ValidationException;

final class CampaignWritesTest extends TestCase
{
    public function test_create_posts_the_payload_and_parses_the_draft(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->campaign(['id' => 42, 'status' => 'draft'])]),
        ], $history);

        $campaign = $client->campaigns()->create([
            'name' => 'July product update',
            'editor' => 'markdown',
            'content' => ['source' => '# Hi'],
            'lists' => [3],
        ]);

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertSame(42, $campaign->id);
        $this->assertSame('draft', $campaign->status);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/campaigns', $request->getUri()->getPath());
        $this->assertSame([
            'name' => 'July product update',
            'editor' => 'markdown',
            'content' => ['source' => '# Hi'],
            'lists' => [3],
        ], json_decode((string) $request->getBody(), true));
    }

    public function test_update_patches_the_draft(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->campaign(['id' => 42, 'subject' => 'Updated'])]),
        ], $history);

        $campaign = $client->campaigns()->update(42, ['subject' => 'Updated']);

        $this->assertSame('Updated', $campaign->subject);

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/42', $request->getUri()->getPath());
        $this->assertSame(['subject' => 'Updated'], json_decode((string) $request->getBody(), true));
    }

    public function test_update_surfaces_the_not_editable_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'Only draft campaigns can be edited.',
                'code' => 'campaign_not_editable',
            ]),
        ], $history);

        try {
            $client->campaigns()->update(42, ['subject' => 'Nope']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('campaign_not_editable', $e->getErrorCode());
            $this->assertSame(422, $e->getStatus());
        }
    }

    public function test_delete_issues_a_delete_and_tolerates_the_empty_204(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(204, []),
        ], $history);

        $client->campaigns()->delete(42);

        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/42', $request->getUri()->getPath());
    }

    public function test_duplicate_sends_no_body_without_a_name(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->campaign(['id' => 43, 'name' => 'July (copy)'])]),
        ], $history);

        $copy = $client->campaigns()->duplicate(42);

        $this->assertSame(43, $copy->id);
        $this->assertSame('July (copy)', $copy->name);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/42/duplicate', $request->getUri()->getPath());
        $this->assertSame('', (string) $request->getBody());
    }

    public function test_duplicate_sends_the_name_when_given(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->campaign(['id' => 44, 'name' => 'Week 38'])]),
        ], $history);

        $client->campaigns()->duplicate(42, 'Week 38');

        $this->assertSame(
            ['name' => 'Week 38'],
            json_decode((string) $history[0]['request']->getBody(), true),
        );
    }

    public function test_send_without_confirmation_never_touches_the_network(): void
    {
        $history = [];
        $client = $this->clientWithResponses([], $history);

        $this->expectException(CampaignSendNotConfirmedException::class);

        try {
            $client->campaigns()->send(42);
        } finally {
            $this->assertSame([], $history, 'A send without confirm: true must not issue any request.');
        }
    }

    public function test_send_with_confirmation_starts_the_campaign(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => $this->campaign([
                'id' => 42,
                'status' => 'sending',
                'recipients_total' => 1840,
                'started_at' => '2026-07-01T12:05:00+00:00',
            ])]),
        ], $history);

        $campaign = $client->campaigns()->send(42, confirm: true);

        $this->assertSame('sending', $campaign->status);
        $this->assertSame(1840, $campaign->recipientsTotal);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/42/send', $request->getUri()->getPath());
    }

    public function test_send_surfaces_the_pre_flight_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The campaign has no recipients.',
                'code' => 'no_recipients',
            ]),
        ], $history);

        try {
            $client->campaigns()->send(42, confirm: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('no_recipients', $e->getErrorCode());
        }
    }

    public function test_schedule_and_unschedule(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->campaign([
                'status' => 'scheduled',
                'scheduled_at' => '2026-07-05T09:00:00+00:00',
            ])]),
            $this->jsonResponse(200, ['data' => $this->campaign(['status' => 'draft'])]),
        ], $history);

        $scheduled = $client->campaigns()->schedule(42, '2026-07-05T09:00:00+00:00');
        $this->assertSame('scheduled', $scheduled->status);
        $this->assertSame('2026-07-05T09:00:00+00:00', $scheduled->scheduledAt);

        $draft = $client->campaigns()->unschedule(42);
        $this->assertSame('draft', $draft->status);

        $this->assertSame('POST', $history[0]['request']->getMethod());
        $this->assertSame('/api/v1/campaigns/42/schedule', $history[0]['request']->getUri()->getPath());
        $this->assertSame(
            ['scheduled_at' => '2026-07-05T09:00:00+00:00'],
            json_decode((string) $history[0]['request']->getBody(), true),
        );

        $this->assertSame('DELETE', $history[1]['request']->getMethod());
        $this->assertSame('/api/v1/campaigns/42/schedule', $history[1]['request']->getUri()->getPath());
    }

    public function test_cancel_returns_the_cancelled_campaign_with_its_counters(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->campaign([
                'status' => 'cancelled',
                'recipients_total' => 1840,
                'dispatched_total' => null,
                'sent_count' => 412,
                'failed_count' => 3,
                'finished_at' => '2026-07-01T12:09:11+00:00',
            ])]),
        ], $history);

        $campaign = $client->campaigns()->cancel(42);

        $this->assertSame('cancelled', $campaign->status);
        $this->assertSame(412, $campaign->sentCount);
        $this->assertNull($campaign->dispatchedTotal);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/42/cancel', $request->getUri()->getPath());
    }

    public function test_cancel_surfaces_the_not_cancellable_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'This campaign cannot be cancelled.',
                'code' => 'campaign_not_cancellable',
            ]),
        ], $history);

        try {
            $client->campaigns()->cancel(42);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('campaign_not_cancellable', $e->getErrorCode());
        }
    }

    public function test_test_send_returns_the_queued_addresses(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['sent_to' => ['me@example.com', 'qa@example.com']]]),
        ], $history);

        $sentTo = $client->campaigns()->testSend(
            42,
            ['me@example.com', 'qa@example.com'],
            contactEmail: 'subscriber@example.com',
            variant: 5,
        );

        $this->assertSame(['me@example.com', 'qa@example.com'], $sentTo);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/42/test-send', $request->getUri()->getPath());
        $this->assertSame([
            'emails' => ['me@example.com', 'qa@example.com'],
            'contact_email' => 'subscriber@example.com',
            'variant' => 5,
        ], json_decode((string) $request->getBody(), true));
    }

    public function test_test_send_omits_the_optional_fields(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['sent_to' => ['me@example.com']]]),
        ], $history);

        $client->campaigns()->testSend(42, ['me@example.com']);

        $this->assertSame(
            ['emails' => ['me@example.com']],
            json_decode((string) $history[0]['request']->getBody(), true),
        );
    }

    public function test_preview_parses_the_rendered_campaign_and_a_null_preheader(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'subject' => 'Your weekly digest, Rita',
                'preheader' => null,
                'html' => '<!doctype html><body>hi</body>',
                'text' => 'hi',
            ]]),
        ], $history);

        $preview = $client->campaigns()->preview(42, contactEmail: 'reader@example.com');

        $this->assertInstanceOf(CampaignPreview::class, $preview);
        $this->assertSame('Your weekly digest, Rita', $preview->subject);
        $this->assertNull($preview->preheader, 'A campaign without a preheader previews as null.');
        $this->assertStringContainsString('<body>', (string) $preview->html);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/42/preview', $request->getUri()->getPath());
        $this->assertSame(
            ['contact_email' => 'reader@example.com'],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function test_preview_without_options_posts_no_body(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['subject' => 'Hi', 'html' => '<p>hi</p>']]),
        ], $history);

        $client->campaigns()->preview(42);

        $this->assertSame('', (string) $history[0]['request']->getBody());
    }

    public function test_readiness_parses_the_checklist(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'ready' => false,
                'schedulable' => true,
                'recipients_total' => 1840,
                'checks' => [
                    ['key' => 'subject', 'passed' => true, 'code' => null, 'message' => null, 'meta' => []],
                    [
                        'key' => 'quota',
                        'passed' => false,
                        'code' => 'quota_exceeded',
                        'message' => 'Not enough quota.',
                        'meta' => ['needed' => 1840, 'limit' => 1000, 'used' => 0],
                    ],
                ],
            ]]),
        ], $history);

        $readiness = $client->campaigns()->readiness(42);

        $this->assertInstanceOf(CampaignReadiness::class, $readiness);
        $this->assertFalse($readiness->ready);
        $this->assertTrue($readiness->schedulable);
        $this->assertSame(1840, $readiness->recipientsTotal);
        $this->assertCount(2, $readiness->checks);

        $failures = $readiness->failures();
        $this->assertCount(1, $failures);
        $this->assertSame('quota', $failures[0]->key);
        $this->assertSame('quota_exceeded', $failures[0]->code);
        $this->assertSame(1840, $failures[0]->meta['needed']);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/42/readiness', $request->getUri()->getPath());
    }

    public function test_recipient_count_passes_the_selection(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['recipients_total' => 1840]]),
        ], $history);

        $total = $client->campaigns()->recipientCount(lists: [3, 7], segments: [2], premium: true);

        $this->assertSame(1840, $total);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/recipient-count', $request->getUri()->getPath());

        $query = urldecode($request->getUri()->getQuery());
        $this->assertStringContainsString('lists[0]=3', $query);
        $this->assertStringContainsString('lists[1]=7', $query);
        $this->assertStringContainsString('segments[0]=2', $query);
        $this->assertStringContainsString('premium=1', $query);
    }

    public function test_recipient_count_surfaces_a_cross_project_list(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'Unknown list.',
                'code' => 'list_not_found',
            ]),
        ], $history);

        try {
            $client->campaigns()->recipientCount(lists: [99]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('list_not_found', $e->getErrorCode());
        }
    }

    public function test_stats_returns_a_map_keyed_by_campaign_id(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                '31' => [
                    'delivered' => 1820,
                    'unique_opens' => 902,
                    'open_rate' => 0.4956,
                    'click_to_open_rate' => 0.3448,
                ],
                '32' => [
                    'delivered' => 0,
                    'unique_opens' => 0,
                    'open_rate' => null,
                    'click_to_open_rate' => null,
                ],
            ]]),
        ], $history);

        $stats = $client->campaigns()->stats([31, 32]);

        $this->assertArrayHasKey(31, $stats);
        $this->assertInstanceOf(CampaignStats::class, $stats[31]);
        $this->assertSame(1820, $stats[31]->delivered);
        $this->assertSame(0.4956, $stats[31]->openRate);
        $this->assertNull($stats[32]->openRate, 'A zero denominator stays null.');

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/stats', $request->getUri()->getPath());

        $query = urldecode($request->getUri()->getQuery());
        $this->assertStringContainsString('ids[0]=31', $query);
        $this->assertStringContainsString('ids[1]=32', $query);
    }

    public function test_list_passes_filters_sort_and_include(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->campaign(['id' => 31, 'stats' => ['delivered' => 10, 'open_rate' => null]])],
                'meta' => ['total' => 1],
                'links' => [],
            ]),
        ], $history);

        $page = $client->campaigns()->list([
            'status' => ['sent', 'failed'],
            'search' => 'July',
            'sort' => '-scheduled_at',
            'include' => 'stats',
            'per_page' => 100,
        ]);

        $this->assertCount(1, $page->data);
        $this->assertInstanceOf(CampaignStats::class, $page->data[0]->stats, 'include=stats embeds stats on list rows.');
        $this->assertSame(10, $page->data[0]->stats->delivered);

        $query = urldecode($history[0]['request']->getUri()->getQuery());
        $this->assertStringContainsString('status[0]=sent', $query);
        $this->assertStringContainsString('status[1]=failed', $query);
        $this->assertStringContainsString('search=July', $query);
        $this->assertStringContainsString('sort=-scheduled_at', $query);
        $this->assertStringContainsString('include=stats', $query);
    }

    public function test_get_with_includes_parses_top_links_and_variants(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->campaign([
                'id' => 31,
                'top_links' => [
                    ['url' => 'https://acme.com/changelog', 'clicks' => 311, 'unique_clicks' => 254],
                ],
                'variants' => [
                    [
                        'id' => 6,
                        'label' => 'B',
                        'subject' => 'June: 11 new things',
                        'is_winner' => true,
                        'sent' => 1748,
                        'delivered' => 1729,
                        'unique_opens' => 862,
                        'unique_clicks' => 302,
                        'open_rate' => 0.4986,
                        'click_rate' => 0.1747,
                    ],
                ],
            ])]),
        ], $history);

        $campaign = $client->campaigns()->get(31, ['include' => 'top_links,variants']);

        $this->assertNotNull($campaign->topLinks);
        $this->assertCount(1, $campaign->topLinks);
        $this->assertSame('https://acme.com/changelog', $campaign->topLinks[0]->url);
        $this->assertSame(254, $campaign->topLinks[0]->uniqueClicks);

        $this->assertNotNull($campaign->variants);
        $this->assertSame('B', $campaign->variants[0]->label);
        $this->assertTrue($campaign->variants[0]->isWinner);
        $this->assertSame(0.4986, $campaign->variants[0]->openRate);

        $query = urldecode($history[0]['request']->getUri()->getQuery());
        $this->assertStringContainsString('include=top_links,variants', $query);
    }

    public function test_get_without_includes_leaves_them_null(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->campaign(['id' => 31])]),
        ], $history);

        $campaign = $client->campaigns()->get(31);

        $this->assertNull($campaign->topLinks, 'Not requested is distinct from requested-but-empty.');
        $this->assertNull($campaign->variants);
        $this->assertSame('', $history[0]['request']->getUri()->getQuery());
    }

    public function test_a_429_maps_to_the_rate_limit_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(429, ['message' => 'Too Many Requests'], ['Retry-After' => '30']),
        ], $history);

        try {
            $client->campaigns()->create(['name' => 'Nope']);
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getStatus());
            $this->assertSame(30, $e->retryAfter());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function campaign(array $overrides = []): array
    {
        return array_merge([
            'id' => 42,
            'name' => 'July product update',
            'subject' => 'What shipped in July',
            'status' => 'draft',
            'recipients_total' => 0,
            'dispatched_total' => null,
            'sent_count' => 0,
            'failed_count' => 0,
            'scheduled_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_at' => '2026-07-01T12:00:00+00:00',
        ], $overrides);
    }
}
