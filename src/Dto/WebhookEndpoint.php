<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * An outbound webhook endpoint of the project.
 *
 * `secret` is populated ONLY by the create response (POST /webhooks) — the API
 * returns the signing secret exactly once and never again, so store it right
 * away; every other endpoint leaves it null.
 */
final readonly class WebhookEndpoint
{
    /**
     * @param  array<int, string>  $events
     */
    public function __construct(
        public ?int $id,
        public ?string $url,
        public array $events,
        public ?bool $enabled,
        public ?int $consecutiveFailures,
        public ?string $disabledAt,
        public ?string $createdAt,
        public ?string $secret,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $events = [];

        if (is_array($data['events'] ?? null)) {
            foreach ($data['events'] as $event) {
                if (is_scalar($event)) {
                    $events[] = (string) $event;
                }
            }
        }

        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            url: isset($data['url']) ? (string) $data['url'] : null,
            events: $events,
            enabled: isset($data['enabled']) ? (bool) $data['enabled'] : null,
            consecutiveFailures: isset($data['consecutive_failures']) ? (int) $data['consecutive_failures'] : null,
            disabledAt: isset($data['disabled_at']) ? (string) $data['disabled_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            secret: isset($data['secret']) ? (string) $data['secret'] : null,
        );
    }
}
