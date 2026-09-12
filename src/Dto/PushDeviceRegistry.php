<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Push device-registry health over the analytics window
 * (GET /notifications/analytics).
 *
 * `prunedInWindow` is dead-token churn only: a voluntary unregister, or a
 * device moving to another contact, is deliberately NOT counted.
 *
 * `devices` and `events` stay raw arrays — `{platform, active, revoked}` and
 * `{date, registered, pruned}` rows — so a new platform or counter never needs
 * an SDK release to reach the caller.
 */
final readonly class PushDeviceRegistry
{
    /**
     * @param  array<int, array<string, mixed>>  $devices  `{platform, active, revoked}` rows.
     * @param  array<int, array<string, mixed>>  $events  `{date, registered, pruned}` rows, zero-filled.
     */
    public function __construct(
        public array $devices,
        public int $activeTotal,
        public array $events,
        public int $registeredInWindow,
        public int $prunedInWindow,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            devices: self::rows($data['devices'] ?? null),
            activeTotal: (int) ($data['active_total'] ?? 0),
            events: self::rows($data['events'] ?? null),
            registeredInWindow: (int) ($data['registered_in_window'] ?? 0),
            prunedInWindow: (int) ($data['pruned_in_window'] ?? 0),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function rows(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $rows = [];

        foreach ($values as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
