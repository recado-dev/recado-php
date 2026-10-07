<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A broadcast: a mass in-app and/or push notification send — the notification
 * sibling of a campaign. Email is deliberately not a broadcast channel.
 *
 * Unlike a campaign a broadcast carries its whole content inline (there is no
 * editor and no rendered HTML), so `title`, `body`, `actionUrl` and `icon` are
 * part of every representation.
 *
 * `channels` comes back normalized by the API: unknown values are dropped and
 * the order is canonical (`in_app`, `push`). `stats` is populated by the detail
 * endpoint and by a list requested with `include=stats`; it is null otherwise.
 *
 * `app` is the key of the push app the push part targets (null = untargeted:
 * the default app and every non-restricted app). It is kept after the app is
 * deleted — such a broadcast is refused at send time with
 * `push_app_not_found`. `push` is the object of native push extras (sound,
 * badge, category, thread_id, interruption_level, android_channel_id, data),
 * null when none are set. Both are null against a server that predates them.
 */
final readonly class Broadcast
{
    /**
     * @param  array<int, string>  $channels
     * @param  array<int, int>  $lists
     * @param  array<int, int>  $segments
     * @param  array<string, mixed>|null  $push
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $title,
        public ?string $body,
        public ?string $actionUrl,
        public ?string $icon,
        public array $channels,
        public ?string $status,
        public array $lists,
        public array $segments,
        public ?int $recipientsTotal,
        public ?int $dispatchedTotal,
        public ?int $sentCount,
        public ?int $failedCount,
        public ?string $scheduledAt,
        public ?string $startedAt,
        public ?string $finishedAt,
        public ?string $createdAt,
        public ?BroadcastStats $stats = null,
        public ?string $app = null,
        public ?array $push = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            body: isset($data['body']) ? (string) $data['body'] : null,
            actionUrl: isset($data['action_url']) ? (string) $data['action_url'] : null,
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            channels: self::strings($data['channels'] ?? null),
            status: isset($data['status']) ? (string) $data['status'] : null,
            lists: self::ids($data['lists'] ?? null),
            segments: self::ids($data['segments'] ?? null),
            recipientsTotal: isset($data['recipients_total']) ? (int) $data['recipients_total'] : null,
            dispatchedTotal: isset($data['dispatched_total']) ? (int) $data['dispatched_total'] : null,
            sentCount: isset($data['sent_count']) ? (int) $data['sent_count'] : null,
            failedCount: isset($data['failed_count']) ? (int) $data['failed_count'] : null,
            scheduledAt: isset($data['scheduled_at']) ? (string) $data['scheduled_at'] : null,
            startedAt: isset($data['started_at']) ? (string) $data['started_at'] : null,
            finishedAt: isset($data['finished_at']) ? (string) $data['finished_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            stats: is_array($data['stats'] ?? null) ? BroadcastStats::fromArray($data['stats']) : null,
            app: isset($data['app']) ? (string) $data['app'] : null,
            push: is_array($data['push'] ?? null) ? $data['push'] : null,
        );
    }

    /**
     * @return array<int, string>
     */
    private static function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $strings = [];

        foreach ($values as $value) {
            if (is_scalar($value)) {
                $strings[] = (string) $value;
            }
        }

        return $strings;
    }

    /**
     * @return array<int, int>
     */
    private static function ids(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $ids = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }
}
