<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A rolling push / in-app aggregation (GET /notifications/analytics) — the
 * answer to "how is the push channel doing?", which the messages endpoint
 * cannot give you because direct API notifications are N loose messages with
 * no campaign to hang stats on.
 *
 * `series` always carries one entry per day of the window, zero-filled, keyed
 * by channel: rows of `{date, api, automation, broadcast}`. It stays a raw
 * array on purpose — a new source would otherwise need an SDK release.
 *
 * Unlike delivery health this DOES work inside a sandbox: intercepted sends are
 * recorded, so it is how you read back a test run.
 */
final readonly class NotificationAnalytics
{
    /**
     * @param  array<string, array<int, array<string, mixed>>>  $series  Keyed by channel.
     * @param  array<string, NotificationChannelStats>  $channels  Keyed by channel.
     */
    public function __construct(
        public int $windowDays,
        public array $series,
        public array $channels,
        public PushDeviceRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $series = [];

        if (is_array($data['series'] ?? null)) {
            foreach ($data['series'] as $channel => $rows) {
                if (! is_array($rows)) {
                    continue;
                }

                $series[(string) $channel] = array_values(array_filter($rows, is_array(...)));
            }
        }

        $channels = [];

        if (is_array($data['channels'] ?? null)) {
            foreach ($data['channels'] as $channel => $stats) {
                if (is_array($stats)) {
                    $channels[(string) $channel] = NotificationChannelStats::fromArray($stats);
                }
            }
        }

        return new self(
            windowDays: (int) ($data['window_days'] ?? 0),
            series: $series,
            channels: $channels,
            registry: PushDeviceRegistry::fromArray(
                is_array($data['registry'] ?? null) ? $data['registry'] : [],
            ),
        );
    }

    /**
     * The stats of one channel (`push`, `in_app`), null when absent.
     */
    public function channel(string $channel): ?NotificationChannelStats
    {
        return $this->channels[$channel] ?? null;
    }
}
