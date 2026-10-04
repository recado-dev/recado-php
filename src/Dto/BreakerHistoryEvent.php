<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * One entry of a sending identity's circuit-breaker history (`history` on
 * GET /delivery/health): its latest events, newest first, at most 20.
 * Resuming an identity clears its `breaker` block but never the history.
 *
 * `type` is one of:
 *
 * - `trip` — the breaker paused the identity. `evaluation` is the snapshot
 *   that tripped it (`metric` `bounce`/`complaint`/`both`, `window_hours`,
 *   `sample`, `bounces`, `complaints`, both rates and the thresholds in force
 *   at that moment) and `autoResume` the verdict on the pause as judged when
 *   it tripped (null for trips recorded before the feature).
 * - `manual_resume` / `auto_resume` — the pause was lifted by a person or
 *   automatically after its cooldown. `resume` carries `tripped_at`,
 *   `restored_status`, `resumed_campaigns` and `resumed_automation_sends`.
 * - `alert` — a transactional reputation alert; NOTHING was paused. `level`
 *   is `warning` or `critical`, `traffic` is `transactional` and `evaluation`
 *   is the snapshot that raised it.
 *
 * `actorType` is `user` (dashboard), `command` (operator CLI) or `system`;
 * the API never says which person. `traffic` is `marketing` for trips and
 * resumes, `transactional` for alerts.
 */
final readonly class BreakerHistoryEvent
{
    /**
     * @param  array<string, mixed>|null  $evaluation
     * @param  array<string, mixed>|null  $resume
     */
    public function __construct(
        public ?int $id,
        public ?string $type,
        public ?string $occurredAt,
        public ?string $traffic,
        public ?string $actorType,
        public ?array $evaluation,
        public ?array $resume,
        public ?string $level,
        public ?BreakerAutoResume $autoResume,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $actor = is_array($data['actor'] ?? null) ? $data['actor'] : [];

        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            type: isset($data['type']) ? (string) $data['type'] : null,
            occurredAt: isset($data['occurred_at']) ? (string) $data['occurred_at'] : null,
            traffic: isset($data['traffic']) ? (string) $data['traffic'] : null,
            actorType: isset($actor['type']) ? (string) $actor['type'] : null,
            evaluation: is_array($data['evaluation'] ?? null) ? $data['evaluation'] : null,
            resume: is_array($data['resume'] ?? null) ? $data['resume'] : null,
            level: isset($data['level']) ? (string) $data['level'] : null,
            autoResume: is_array($data['auto_resume'] ?? null)
                ? BreakerAutoResume::fromArray($data['auto_resume'])
                : null,
        );
    }

    public function isTrip(): bool
    {
        return $this->type === 'trip';
    }

    /**
     * Whether the event lifted a pause, by hand or automatically.
     */
    public function isResume(): bool
    {
        return $this->type === 'manual_resume' || $this->type === 'auto_resume';
    }

    public function isAutomaticResume(): bool
    {
        return $this->type === 'auto_resume';
    }

    public function isAlert(): bool
    {
        return $this->type === 'alert';
    }
}
