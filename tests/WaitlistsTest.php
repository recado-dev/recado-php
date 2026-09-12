<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\ValidationException;

/**
 * The waitlists surface: the read side plus the one irreversible action.
 */
final class WaitlistsTest extends TestCase
{
    public function test_it_lists_waitlists_and_maps_the_opt_in_stats_block(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [
                    $this->waitlist(),
                    $this->waitlist(['id' => 8, 'slug' => 'beta', 'status' => 'launched', 'stats' => null]),
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2],
                'links' => [],
            ]),
        ], $history);

        $page = $client->waitlists()->list(['status' => 'open', 'include' => 'stats']);

        $this->assertCount(2, $page->data);
        $this->assertSame('launch', $page->data[0]->slug);
        $this->assertFalse($page->data[0]->isLaunched());
        $this->assertTrue($page->data[1]->isLaunched());

        // `include=stats` is opt-in, so an item without the block reports null
        // rather than a zeroed one.
        $this->assertSame(412, $page->data[0]->stats->total);
        $this->assertSame(18.7, $page->data[0]->stats->referredShare);
        $this->assertNull($page->data[1]->stats);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/waitlists', $request->getUri()->getPath());
        $this->assertSame('status=open&include=stats', $request->getUri()->getQuery());
    }

    public function test_it_fetches_one_waitlist_and_its_ranked_members(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->waitlist()]),
            $this->jsonResponse(200, [
                'data' => [
                    [
                        'id' => 1, 'uuid' => 'c0ffee00-0000-4000-8000-000000000001',
                        'contact_id' => 44, 'email' => 'ada@example.com',
                        'position' => 1, 'referral_code' => 'ab12cd34',
                        'referred_by_id' => null, 'referrals_count' => 9,
                        'confirmed_at' => '2026-09-01T10:00:00+00:00',
                        'created_at' => '2026-09-01T09:00:00+00:00',
                    ],
                    [
                        'id' => 2, 'uuid' => 'c0ffee00-0000-4000-8000-000000000002',
                        'contact_id' => 45, 'email' => 'grace@example.com',
                        'position' => 7, 'referral_code' => 'ef56gh78',
                        'referred_by_id' => 1, 'referrals_count' => 0,
                        'confirmed_at' => null,
                        'created_at' => '2026-09-02T09:00:00+00:00',
                    ],
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2],
            ]),
        ], $history);

        $waitlist = $client->waitlists()->get(7);
        $this->assertSame(7, $waitlist->id);
        $this->assertSame('https://acme.recado.dev/w/launch', $waitlist->publicUrl);
        $this->assertSame(12, $waitlist->listId);

        $members = $client->waitlists()->members(7, ['email' => 'example.com']);

        // The filter is applied AFTER ranking, so a matched member keeps the
        // position it holds on the FULL list — 7, not 2.
        $this->assertSame(1, $members->data[0]->position);
        $this->assertSame(7, $members->data[1]->position);
        $this->assertFalse($members->data[0]->wasReferred());
        $this->assertTrue($members->data[1]->wasReferred());

        $this->assertSame('/api/v1/waitlists/7', $history[0]['request']->getUri()->getPath());
        $this->assertSame('/api/v1/waitlists/7/members', $history[1]['request']->getUri()->getPath());
        $this->assertSame('email=example.com', $history[1]['request']->getUri()->getQuery());
    }

    public function test_the_members_cursor_walks_every_page(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [['id' => 1, 'position' => 1]],
                'meta' => ['current_page' => 1, 'last_page' => 2],
            ]),
            $this->jsonResponse(200, [
                'data' => [['id' => 2, 'position' => 2]],
                'meta' => ['current_page' => 2, 'last_page' => 2],
            ]),
        ], $history);

        $ids = [];

        foreach ($client->waitlists()->membersCursor(7) as $member) {
            $ids[] = $member->id;
        }

        $this->assertSame([1, 2], $ids);
        $this->assertCount(2, $history);
    }

    public function test_launch_sends_the_campaign_flag_and_returns_the_launched_waitlist(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->waitlist([
                'status' => 'launched',
                'launched_at' => '2026-09-12T12:00:00+00:00',
                'launch_campaign_id' => 91,
            ])]),
        ], $history);

        $waitlist = $client->waitlists()->launch(7);

        $this->assertTrue($waitlist->isLaunched());
        $this->assertSame(91, $waitlist->launchCampaignId);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/waitlists/7/launch', $request->getUri()->getPath());
        $this->assertSame(['create_campaign' => true], json_decode((string) $request->getBody(), true));
    }

    public function test_launch_can_skip_the_announcement_campaign(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->waitlist(['status' => 'launched'])]),
        ], $history);

        $client->waitlists()->launch(7, createCampaign: false);

        $this->assertSame(
            ['create_campaign' => false],
            json_decode((string) $history[0]['request']->getBody(), true),
        );
    }

    public function test_a_second_launch_is_refused_and_an_unknown_id_is_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'This waitlist has already launched.',
                'code' => 'waitlist_already_launched',
            ]),
            $this->jsonResponse(404, [
                'message' => 'Waitlist not found.',
                'code' => 'waitlist_not_found',
            ]),
        ], $history);

        try {
            $client->waitlists()->launch(7);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            // There is no unlaunch, so the second call can only ever be refused.
            $this->assertSame('waitlist_already_launched', $e->getErrorCode());
        }

        try {
            $client->waitlists()->get(999);
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('waitlist_not_found', $e->getErrorCode());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function waitlist(array $overrides = []): array
    {
        return array_merge([
            'id' => 7,
            'name' => 'Launch list',
            'slug' => 'launch',
            'title' => 'Join the launch',
            'description' => 'Be first.',
            'status' => 'open',
            'show_count' => true,
            'referrals_enabled' => true,
            'post_launch_redirect_url' => null,
            'public_url' => 'https://acme.recado.dev/w/launch',
            'list_id' => 12,
            'launch_campaign_id' => null,
            'members_count' => 412,
            'launched_at' => null,
            'created_at' => '2026-08-01T09:00:00+00:00',
            'stats' => [
                'total' => 412, 'last_7_days' => 38,
                'referred' => 77, 'referred_share' => 18.7,
            ],
        ], $overrides);
    }
}
