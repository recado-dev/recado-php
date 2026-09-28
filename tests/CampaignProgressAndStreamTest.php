<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Campaign;

final class CampaignProgressAndStreamTest extends TestCase
{
    public function test_campaign_detail_carries_progress(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'id' => 42,
                'status' => 'sending',
                'progress' => ['pending' => 1200, 'skipped' => null],
            ]]),
            $this->jsonResponse(200, ['data' => [
                'id' => 42,
                'status' => 'sent',
                'progress' => ['pending' => 0, 'skipped' => 17],
            ]]),
        ], $history);

        $sending = $client->campaigns()->get(42);
        $sent = $client->campaigns()->get(42);

        $this->assertNotNull($sending->progress);
        $this->assertSame(1200, $sending->progress->pending);
        $this->assertNull($sending->progress->skipped);
        $this->assertSame(0, $sent->progress?->pending);
        $this->assertSame(17, $sent->progress?->skipped);
    }

    public function test_the_listing_form_has_no_progress(): void
    {
        $this->assertNull(Campaign::fromArray(['id' => 42])->progress);
    }

    public function test_add_sends_the_stream_only_when_given(): void
    {
        $history = [];
        $domain = ['id' => 3, 'domain' => 'news.acme.com', 'status' => 'pending', 'records' => []];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $domain]),
            $this->jsonResponse(201, ['data' => $domain]),
        ], $history);

        $client->sendingDomains()->add('news.acme.com', stream: 'marketing');
        $client->sendingDomains()->add('mail.acme.com');

        $this->assertSame(
            ['domain' => 'news.acme.com', 'stream' => 'marketing'],
            json_decode((string) $history[0]['request']->getBody(), true),
        );
        $this->assertSame(
            ['domain' => 'mail.acme.com'],
            json_decode((string) $history[1]['request']->getBody(), true),
        );
    }
}
