<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Campaign;
use Recado\Sdk\Dto\CampaignStats;

final class CampaignsTest extends TestCase
{
    public function test_list_parses_paginated_campaigns_and_passes_filters(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [
                    [
                        'id' => 1,
                        'name' => 'Spring sale',
                        'subject' => '20% off',
                        'status' => 'sent',
                        'recipients_total' => 100,
                        'sent_count' => 98,
                        'failed_count' => 2,
                    ],
                ],
                'meta' => ['total' => 1, 'current_page' => 1],
                'links' => [],
            ]),
        ], $history);

        $page = $client->campaigns()->list(['per_page' => 50]);

        $this->assertCount(1, $page->data);
        $this->assertContainsOnlyInstancesOf(Campaign::class, $page->data);
        $this->assertSame(1, $page->data[0]->id);
        $this->assertSame('Spring sale', $page->data[0]->name);
        $this->assertSame('sent', $page->data[0]->status);
        $this->assertSame(98, $page->data[0]->sentCount);
        $this->assertNull($page->data[0]->stats, 'List items do not embed stats.');
        $this->assertSame(1, $page->meta['total']);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/campaigns', $request->getUri()->getPath());
        $this->assertStringContainsString('per_page=50', $request->getUri()->getQuery());
    }

    public function test_get_parses_campaign_with_stats_and_null_rates(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [
                    'id' => 7,
                    'name' => 'Welcome series',
                    'subject' => 'Hello there',
                    'status' => 'sent',
                    'recipients_total' => 200,
                    'dispatched_total' => 200,
                    'sent_count' => 200,
                    'failed_count' => 0,
                    'scheduled_at' => null,
                    'started_at' => '2026-01-01T00:00:00Z',
                    'finished_at' => '2026-01-01T00:05:00Z',
                    'created_at' => '2025-12-31T00:00:00Z',
                    'stats' => [
                        'delivered' => 198,
                        'unique_opens' => 120,
                        'total_opens' => 240,
                        'unique_clicks' => 60,
                        'total_clicks' => 90,
                        'bounced' => 2,
                        'complained' => 0,
                        'unsubscribed' => 1,
                        'open_rate' => 0.6061,
                        'click_rate' => 0.303,
                        'click_to_open_rate' => null,
                    ],
                ],
            ]),
        ], $history);

        $campaign = $client->campaigns()->get(7);

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertSame(7, $campaign->id);
        $this->assertSame('Welcome series', $campaign->name);
        $this->assertSame('sent', $campaign->status);
        $this->assertSame('2026-01-01T00:00:00Z', $campaign->startedAt);

        $this->assertInstanceOf(CampaignStats::class, $campaign->stats);
        $this->assertSame(198, $campaign->stats->delivered);
        $this->assertSame(120, $campaign->stats->uniqueOpens);
        $this->assertSame(240, $campaign->stats->totalOpens);
        $this->assertSame(60, $campaign->stats->uniqueClicks);
        $this->assertSame(2, $campaign->stats->bounced);
        $this->assertSame(1, $campaign->stats->unsubscribed);
        $this->assertSame(0.6061, $campaign->stats->openRate);
        $this->assertSame(0.303, $campaign->stats->clickRate);
        $this->assertNull($campaign->stats->clickToOpenRate, 'A null rate stays null.');

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/7', $request->getUri()->getPath());
    }

    public function test_cursor_walks_all_pages_in_order(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, $this->campaignsPage([$this->campaign(1), $this->campaign(2)], 1, 3)),
            $this->jsonResponse(200, $this->campaignsPage([$this->campaign(3), $this->campaign(4)], 2, 3)),
            $this->jsonResponse(200, $this->campaignsPage([$this->campaign(5)], 3, 3)),
        ], $history);

        $ids = [];

        foreach ($client->campaigns()->cursor(['per_page' => 2]) as $campaign) {
            $this->assertInstanceOf(Campaign::class, $campaign);
            $ids[] = $campaign->id;
        }

        $this->assertSame([1, 2, 3, 4, 5], $ids);
        $this->assertCount(3, $history, 'Expected exactly one request per page.');

        foreach ([1, 2, 3] as $i => $expectedPage) {
            $query = $history[$i]['request']->getUri()->getQuery();
            $this->assertStringContainsString('page='.$expectedPage, $query);
            $this->assertStringContainsString('per_page=2', $query);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $campaigns
     *
     * @return array<string, mixed>
     */
    private function campaignsPage(array $campaigns, int $currentPage, int $lastPage): array
    {
        return [
            'data' => $campaigns,
            'meta' => ['current_page' => $currentPage, 'last_page' => $lastPage, 'per_page' => 2, 'total' => 5],
            'links' => ['next' => $currentPage < $lastPage ? 'next-url' : null],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaign(int $id): array
    {
        return [
            'id' => $id,
            'name' => 'Campaign '.$id,
            'subject' => 'Subject '.$id,
            'status' => 'sent',
        ];
    }
}
