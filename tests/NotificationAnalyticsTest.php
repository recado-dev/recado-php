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
}
