<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * One row of the per-app push breakdown (GET /notifications/analytics →
 * `apps.targets`).
 *
 * `target` says what the row is: `default` (the project's implicit default
 * app), `app` (a push app — `key`, `name`, `transport`, `restricted`,
 * `enabled`) or `web` (web push subscriptions, which never belong to an
 * app). An `app` row with `deleted` true is an app that no longer exists but
 * still has history in the window: it is named by the key it had, and its
 * `name`, `transport`, `restricted` and `enabled` are null.
 *
 * `sends` holds the stats of the messages that TARGETED the app, with the
 * same silent rule as the channel totals. It is null for `default` and
 * `web`: no send can name them. A send that named no app is never split
 * across rows — see {@see PushAppAnalytics::$untargeted}.
 *
 * `events` stays a raw array of `{date, registered, pruned}` rows,
 * zero-filled, like the registry's.
 */
final readonly class PushAppTargetStats
{
    /**
     * @param  array<int, array<string, mixed>>  $events  `{date, registered, pruned}` rows, zero-filled.
     */
    public function __construct(
        public string $target,
        public ?string $key,
        public ?string $name,
        public ?string $transport,
        public ?bool $restricted,
        public ?bool $enabled,
        public bool $deleted,
        public int $activeDevices,
        public int $revokedDevices,
        public int $registeredInWindow,
        public int $prunedInWindow,
        public array $events,
        public ?NotificationChannelStats $sends,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $devices = is_array($data['devices'] ?? null) ? $data['devices'] : [];

        $events = [];

        if (is_array($data['events'] ?? null)) {
            foreach ($data['events'] as $row) {
                if (is_array($row)) {
                    $events[] = $row;
                }
            }
        }

        return new self(
            target: (string) ($data['target'] ?? ''),
            key: isset($data['key']) ? (string) $data['key'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            transport: isset($data['transport']) ? (string) $data['transport'] : null,
            restricted: isset($data['restricted']) ? (bool) $data['restricted'] : null,
            enabled: isset($data['enabled']) ? (bool) $data['enabled'] : null,
            deleted: (bool) ($data['deleted'] ?? false),
            activeDevices: (int) ($devices['active'] ?? 0),
            revokedDevices: (int) ($devices['revoked'] ?? 0),
            registeredInWindow: (int) ($data['registered_in_window'] ?? 0),
            prunedInWindow: (int) ($data['pruned_in_window'] ?? 0),
            events: $events,
            sends: is_array($data['sends'] ?? null) ? NotificationChannelStats::fromArray($data['sends']) : null,
        );
    }
}
