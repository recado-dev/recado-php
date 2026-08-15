<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A single per-recipient result inside a batch notification response
 * (POST /notifications/batch).
 *
 * One item fans out to one result per requested channel, so the outcomes
 * live in {@see $results} rather than on the item itself.
 */
final readonly class NotificationBatchItem
{
    /**
     * @param array<int, NotificationChannelResult> $results
     */
    public function __construct(
        public ?int $index,
        public ?string $to,
        public array $results,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $results = [];

        foreach ($data['results'] ?? [] as $result) {
            if (is_array($result)) {
                $results[] = NotificationChannelResult::fromArray($result);
            }
        }

        return new self(
            index: isset($data['index']) ? (int) $data['index'] : null,
            to: isset($data['to']) ? (string) $data['to'] : null,
            results: $results,
        );
    }

    /**
     * Whether at least one channel of this item was accepted for delivery.
     */
    public function anyQueued(): bool
    {
        foreach ($this->results as $result) {
            if ($result->queued()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The result for a given channel, or null when it was not requested.
     */
    public function channel(string $channel): ?NotificationChannelResult
    {
        foreach ($this->results as $result) {
            if ($result->channel === $channel) {
                return $result;
            }
        }

        return null;
    }
}
