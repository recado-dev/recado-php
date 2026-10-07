<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

/**
 * GET /notifications/analytics — the rolling push / in-app aggregation.
 */
final class NotificationAnalyticsTest extends TestCase
{
    public function test_analytics_maps_the_window_series_channels_and_registry(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'window_days' => 30,
                'series' => [
                    'push' => [['date' => '2026-09-11', 'api' => 120, 'automation' => 4, 'broadcast' => 0]],
                    'in_app' => [['date' => '2026-09-11', 'api' => 40, 'automation' => 0, 'broadcast' => 900]],
                ],
                'channels' => [
                    'push' => [
                        'total' => 3800, 'queued' => 2, 'sent' => 3760, 'failed' => 38,
                        'by_source' => ['api' => 3600, 'automation' => 200, 'broadcast' => 0],
                        'delivered' => 3700, 'opened' => 910, 'clicked' => 240,
                        'delivery_rate' => 0.984, 'open_rate' => 0.2459, 'click_rate' => 0.0648,
                        'silent' => 12,
                    ],
                    'in_app' => [
                        'total' => 0, 'queued' => 0, 'sent' => 0, 'failed' => 0,
                        'by_source' => ['api' => 0, 'automation' => 0, 'broadcast' => 0],
                        'delivered' => 0, 'opened' => 0, 'clicked' => 0,
                        'delivery_rate' => null, 'open_rate' => null, 'click_rate' => null,
                    ],
                ],
                'registry' => [
                    'devices' => [['platform' => 'ios', 'active' => 820, 'revoked' => 14]],
                    'active_total' => 1240,
                    'events' => [['date' => '2026-09-11', 'registered' => 12, 'pruned' => 3]],
                    'registered_in_window' => 310,
                    'pruned_in_window' => 46,
                ],
            ]]),
        ], $history);

        $analytics = $client->notifications()->analytics();

        $this->assertSame(30, $analytics->windowDays);
        $this->assertSame(120, $analytics->series['push'][0]['api']);

        $push = $analytics->channel('push');
        $this->assertSame(3760, $push->sent);
        $this->assertSame(3600, $push->bySource['api']);
        $this->assertSame(0.984, $push->deliveryRate);
        // Silent pushes are reported on their own; a payload from a server
        // that predates the key reads as 0.
        $this->assertSame(12, $push->silent);
        $this->assertSame(0, $analytics->channel('in_app')?->silent);

        // A zero denominator stays null, never a fake 0.0 — the same rule as
        // campaign and broadcast stats.
        $this->assertNull($analytics->channel('in_app')->openRate);
        $this->assertNull($analytics->channel('email'));

        $this->assertSame(1240, $analytics->registry->activeTotal);
        $this->assertSame('ios', $analytics->registry->devices[0]['platform']);
        // Dead-token churn only: a voluntary unregister is not counted here.
        $this->assertSame(46, $analytics->registry->prunedInWindow);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/notifications/analytics', $request->getUri()->getPath());
    }

    public function test_analytics_maps_the_per_app_breakdown(): void
    {
        $stats = fn (array $overrides = []): array => [
            'total' => 0, 'queued' => 0, 'sent' => 0, 'failed' => 0,
            'delivered' => 0, 'opened' => 0, 'clicked' => 0,
            'delivery_rate' => null, 'open_rate' => null, 'click_rate' => null,
            'silent' => 0,
            ...$overrides,
        ];

        $target = fn (array $overrides = []): array => [
            'target' => 'app', 'key' => null, 'name' => null, 'transport' => null,
            'restricted' => null, 'enabled' => null, 'deleted' => false,
            'devices' => ['active' => 0, 'revoked' => 0],
            'registered_in_window' => 0, 'pruned_in_window' => 0,
            'events' => [['date' => '2026-10-07', 'registered' => 0, 'pruned' => 0]],
            'sends' => null,
            ...$overrides,
        ];

        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'window_days' => 30,
                'series' => [],
                'channels' => [],
                'registry' => [],
                'apps' => [
                    'targets' => [
                        $target([
                            'target' => 'default', 'transport' => 'fcm', 'restricted' => false,
                            'devices' => ['active' => 900, 'revoked' => 12],
                        ]),
                        $target([
                            'key' => 'admin', 'name' => 'Admin', 'transport' => 'apns',
                            'restricted' => true, 'enabled' => true,
                            'devices' => ['active' => 14, 'revoked' => 1],
                            'registered_in_window' => 3, 'pruned_in_window' => 2,
                            'sends' => $stats([
                                'total' => 42, 'sent' => 40, 'failed' => 2, 'delivered' => 38,
                                'opened' => 19, 'clicked' => 4, 'delivery_rate' => 0.95,
                                'open_rate' => 0.5278, 'click_rate' => 0.1111, 'silent' => 2,
                            ]),
                        ]),
                        $target(['target' => 'web', 'transport' => 'web_push', 'restricted' => false]),
                        $target([
                            'key' => 'legacy', 'deleted' => true, 'pruned_in_window' => 7,
                            'sends' => $stats(['total' => 5, 'sent' => 5]),
                        ]),
                    ],
                    'untargeted' => $stats(['total' => 3000, 'sent' => 2990, 'delivered' => 2900, 'open_rate' => 0.21]),
                ],
            ]]),
            // A project without push apps: the key is absent.
            $this->jsonResponse(200, ['data' => ['window_days' => 30, 'series' => [], 'channels' => [], 'registry' => []]]),
        ], $history);

        $apps = $client->notifications()->analytics()->apps;

        $this->assertNotNull($apps);
        $this->assertCount(4, $apps->targets);

        $admin = $apps->app('admin');
        $this->assertSame('Admin', $admin->name);
        $this->assertSame('apns', $admin->transport);
        $this->assertTrue($admin->restricted);
        $this->assertSame(14, $admin->activeDevices);
        $this->assertSame(1, $admin->revokedDevices);
        $this->assertSame(3, $admin->registeredInWindow);
        $this->assertSame(2, $admin->prunedInWindow);
        $this->assertSame(40, $admin->sends->sent);
        $this->assertSame(0.5278, $admin->sends->openRate);
        $this->assertSame(2, $admin->sends->silent);
        $this->assertSame('2026-10-07', $admin->events[0]['date']);

        // No send can name the default app or web push: their sends are null.
        $this->assertNull($apps->defaultApp()->sends);
        $this->assertSame(900, $apps->defaultApp()->activeDevices);
        $this->assertNull($apps->web()->sends);

        // A deleted app keeps its key and history, and is not a live app.
        $this->assertNull($apps->app('legacy'));
        $legacy = $apps->targets[3];
        $this->assertTrue($legacy->deleted);
        $this->assertSame('legacy', $legacy->key);
        $this->assertNull($legacy->name);
        $this->assertNull($legacy->restricted);
        $this->assertSame(7, $legacy->prunedInWindow);

        // Sends that named no app are one bucket, never split across apps.
        $this->assertSame(2990, $apps->untargeted->sent);
        $this->assertSame(0.21, $apps->untargeted->openRate);
        $this->assertNull($apps->untargeted->clickRate);

        $this->assertNull($client->notifications()->analytics()->apps);
    }
}
