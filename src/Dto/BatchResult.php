<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Result of a batch send (POST /send/batch).
 */
final readonly class BatchResult
{
    /**
     * @param array<int, BatchItem> $messages
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
        $items = [];

        foreach ($data['messages'] ?? [] as $message) {
            if (is_array($message)) {
                $items[] = BatchItem::fromArray($message);
            }
        }

        return new self(
            messages: $items,
            queued: (int) ($data['queued'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
        );
    }
}
