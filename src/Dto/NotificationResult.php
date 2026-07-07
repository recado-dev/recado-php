<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Result of a notification send (POST /notifications).
 *
 * The per-channel envelope is data, not control flow: a channel that failed a
 * precondition (e.g. push not configured) is reported as a
 * {@see NotificationChannelResult} with an error code, exactly like a batch
 * send reports per-message failures.
 */
final readonly class NotificationResult
{
    /**
     * @param array<int, NotificationChannelResult> $messages
     */
    public function __construct(
        public array $messages,
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
                $messages[] = NotificationChannelResult::fromArray($message);
            }
        }

        return new self(messages: $messages);
    }

    /**
     * Whether at least one channel was accepted for delivery.
     */
    public function anyQueued(): bool
    {
        foreach ($this->messages as $message) {
            if ($message->queued()) {
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
        foreach ($this->messages as $message) {
            if ($message->channel === $channel) {
                return $message;
            }
        }

        return null;
    }
}
