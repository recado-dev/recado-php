<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Broadcast;
use Recado\Sdk\Exception\CampaignSendNotConfirmedException;
use Recado\Sdk\Exception\ValidationException;

/**
 * The broadcasts resource: the mass in-app / push lifecycle, including the
 * local confirmation guard in front of send().
 */
final class BroadcastsTest extends TestCase
{
    public function test_list_maps_the_rows_and_passes_the_filters(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->broadcast()],
                'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1],
                'links' => ['next' => null],
            ]),
        ], $history);

        $page = $client->broadcasts()->list(['status' => ['sent', 'sending'], 'include' => 'stats']);

        $this->assertCount(1, $page->data);
        $this->assertContainsOnlyInstancesOf(Broadcast::class, $page->data);
        $this->assertSame(1, $page->meta['total']);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/broadcasts', $request->getUri()->getPath());
        $this->assertStringContainsString('status%5B0%5D=sent', $request->getUri()->getQuery());
        $this->assertStringContainsString('include=stats', $request->getUri()->getQuery());
    }

    public function test_get_maps_the_inline_content_targeting_and_stats(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->broadcast([
                'stats' => [
                    'queued' => 2, 'sent' => 2450, 'failed' => 0,
                    'delivered' => 2400, 'opened' => 600, 'clicked' => 120,
                    'delivery_rate' => 0.9796, 'open_rate' => 0.25, 'click_rate' => null,
                ],
            ])]),
        ], $history);

        $broadcast = $client->broadcasts()->get(12);

        $this->assertSame('We launched', $broadcast->title);
        $this->assertSame('myapp://dashboard', $broadcast->actionUrl);
        $this->assertSame(['in_app', 'push'], $broadcast->channels);
        $this->assertSame([3], $broadcast->lists);
        $this->assertSame([7], $broadcast->segments);
        $this->assertSame(2400, $broadcast->stats->delivered);
        // A zero denominator stays null rather than a misleading 0.0.
        $this->assertNull($broadcast->stats->clickRate);
    }

    public function test_create_and_update_post_the_payload_verbatim(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->broadcast()]),
            $this->jsonResponse(200, ['data' => $this->broadcast(['channels' => ['push']])]),
        ], $history);

        $client->broadcasts()->create([
            'name' => 'Launch day',
            'title' => 'We launched',
            'channels' => ['in_app', 'push'],
            'lists' => [3],
        ]);

        $updated = $client->broadcasts()->update(12, ['channels' => ['push']]);

        $this->assertSame(['push'], $updated->channels);

        $create = $history[0]['request'];
        $this->assertSame('POST', $create->getMethod());
        $this->assertSame('/api/v1/broadcasts', $create->getUri()->getPath());
        $this->assertSame([
            'name' => 'Launch day',
            'title' => 'We launched',
            'channels' => ['in_app', 'push'],
            'lists' => [3],
        ], json_decode((string) $create->getBody(), true));

        $update = $history[1]['request'];
        $this->assertSame('PATCH', $update->getMethod());
        $this->assertSame('/api/v1/broadcasts/12', $update->getUri()->getPath());
    }

    public function test_send_without_confirmation_never_reaches_the_api(): void
    {
        $history = [];
        // No queued response on purpose: a request here would blow up the mock.
        $client = $this->clientWithResponses([], $history);

        $this->expectException(CampaignSendNotConfirmedException::class);

        try {
            $client->broadcasts()->send(12);
        } finally {
            $this->assertSame([], $history, 'The guard must fire before any HTTP request.');
        }
    }

    public function test_send_with_confirmation_posts_and_returns_the_sending_broadcast(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => $this->broadcast([
                'status' => 'sending',
                'recipients_total' => 2452,
                'started_at' => '2026-09-11T12:05:00+00:00',
            ])]),
        ], $history);

        $broadcast = $client->broadcasts()->send(12, confirm: true);

        $this->assertSame('sending', $broadcast->status);
        // The sum across channels: a contact reachable twice is two
        // notifications, not one person.
        $this->assertSame(2452, $broadcast->recipientsTotal);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/broadcasts/12/send', $request->getUri()->getPath());
    }

    public function test_schedule_unschedule_and_cancel_hit_their_verbs(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->broadcast(['status' => 'scheduled'])]),
            $this->jsonResponse(200, ['data' => $this->broadcast(['status' => 'draft'])]),
            $this->jsonResponse(200, ['data' => $this->broadcast(['status' => 'cancelled'])]),
        ], $history);

        $this->assertSame('scheduled', $client->broadcasts()->schedule(12, '2026-12-01T09:00:00Z')->status);
        $this->assertSame('draft', $client->broadcasts()->unschedule(12)->status);
        $this->assertSame('cancelled', $client->broadcasts()->cancel(12)->status);

        $this->assertSame('POST', $history[0]['request']->getMethod());
        $this->assertSame('/api/v1/broadcasts/12/schedule', $history[0]['request']->getUri()->getPath());
        $this->assertSame(
            ['scheduled_at' => '2026-12-01T09:00:00Z'],
            json_decode((string) $history[0]['request']->getBody(), true),
        );

        $this->assertSame('DELETE', $history[1]['request']->getMethod());
        $this->assertSame('/api/v1/broadcasts/12/schedule', $history[1]['request']->getUri()->getPath());

        $this->assertSame('POST', $history[2]['request']->getMethod());
        $this->assertSame('/api/v1/broadcasts/12/cancel', $history[2]['request']->getUri()->getPath());
    }

    public function test_test_send_targets_one_contact_and_returns_the_channels(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'sent_to' => 'reader@example.com',
                'channels' => ['in_app', 'push'],
            ]]),
        ], $history);

        $channels = $client->broadcasts()->testSend(12, 'reader@example.com');

        $this->assertSame(['in_app', 'push'], $channels);

        $request = $history[0]['request'];
        $this->assertSame('/api/v1/broadcasts/12/test-send', $request->getUri()->getPath());
        $this->assertSame(['to' => 'reader@example.com'], json_decode((string) $request->getBody(), true));
    }

    public function test_recipient_count_reports_every_channel_and_the_sum(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'counts' => ['in_app' => 1840, 'push' => 612],
                'recipients_total' => 2452,
            ]]),
        ], $history);

        $counts = $client->broadcasts()->recipientCount([3], [7]);

        $this->assertSame(1840, $counts->for('in_app'));
        $this->assertSame(612, $counts->for('push'));
        $this->assertSame(2452, $counts->recipientsTotal);
        // A channel the project does not use is simply absent, not an error.
        $this->assertSame(0, $counts->for('email'));

        $query = $history[0]['request']->getUri()->getQuery();
        $this->assertStringContainsString('lists%5B0%5D=3', $query);
        $this->assertStringContainsString('segments%5B0%5D=7', $query);
    }

    public function test_a_refused_send_keeps_its_machine_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'Push is not available on this plan.',
                'code' => 'push_not_entitled',
            ]),
        ], $history);

        try {
            $client->broadcasts()->send(12, confirm: true);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('push_not_entitled', $e->getErrorCode());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function broadcast(array $overrides = []): array
    {
        return array_merge([
            'id' => 12,
            'name' => 'Launch day',
            'title' => 'We launched',
            'body' => 'The new dashboard is live.',
            'action_url' => 'myapp://dashboard',
            'icon' => 'https://cdn.acme.com/icon.png',
            'channels' => ['in_app', 'push'],
            'status' => 'draft',
            'lists' => [3],
            'segments' => [7],
            'recipients_total' => 0,
            'dispatched_total' => null,
            'sent_count' => 0,
            'failed_count' => 0,
            'scheduled_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_at' => '2026-09-11T12:00:00+00:00',
        ], $overrides);
    }
}
