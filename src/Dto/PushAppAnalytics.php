<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Push analytics broken down by app (GET /notifications/analytics → `apps`).
 * Only present for a project that has at least one push app.
 *
 * Attribution: a send that names an app belongs to that app's row; a send
 * that names none is reported in `untargeted` and in no row. It fans out to
 * several apps but is one notification with one set of opens and clicks, so
 * the API does not pretend to know which app they came from.
 *
 * `untargeted` reuses {@see NotificationChannelStats}; its `bySource` is
 * always empty (the breakdown carries no per-source split).
 */
final readonly class PushAppAnalytics
{
    /**
     * @param  array<int, PushAppTargetStats>  $targets  Default app, each push app by name, web push, then deleted apps.
     */
    public function __construct(
        public array $targets,
        public NotificationChannelStats $untargeted,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $targets = [];

        if (is_array($data['targets'] ?? null)) {
            foreach ($data['targets'] as $target) {
                if (is_array($target)) {
                    $targets[] = PushAppTargetStats::fromArray($target);
                }
            }
        }

        return new self(
            targets: $targets,
            untargeted: NotificationChannelStats::fromArray(
                is_array($data['untargeted'] ?? null) ? $data['untargeted'] : [],
            ),
        );
    }

    /**
     * The row of a LIVE push app by its key, null when there is none.
     */
    public function app(string $key): ?PushAppTargetStats
    {
        foreach ($this->targets as $target) {
            if ($target->target === 'app' && $target->key === $key && ! $target->deleted) {
                return $target;
            }
        }

        return null;
    }

    /**
     * The row of the project's default app.
     */
    public function defaultApp(): ?PushAppTargetStats
    {
        return $this->first('default');
    }

    /**
     * The row of web push subscriptions.
     */
    public function web(): ?PushAppTargetStats
    {
        return $this->first('web');
    }

    private function first(string $type): ?PushAppTargetStats
    {
        foreach ($this->targets as $target) {
            if ($target->target === $type) {
                return $target;
            }
        }

        return null;
    }
}
