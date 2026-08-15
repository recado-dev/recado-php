<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Result of a batch notification send (POST /notifications/batch).
 *
 * `queued` and `failed` count CHANNEL dispatches, not items: a two-channel
 * item whose push failed contributes to both counters.
 */
final readonly class NotificationBatchResult
{
    /**
     * @param array<int, NotificationBatchItem> $messages
     */
    public function __construct(
        public array $messages,
        public int $queued,
        public int $failed,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $messages = [];

        foreach ($data['messages'] ?? [] as $message) {
            if (is_array($message)) {
                $messages[] = NotificationBatchItem::fromArray($message);
            }
        }

        return new self(
            messages: $messages,
            queued: (int) ($data['queued'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
        );
    }
}
