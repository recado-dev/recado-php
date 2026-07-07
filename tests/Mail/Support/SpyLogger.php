<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests\Mail\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Minimal PSR-3 logger that records every log call for assertions.
 */
final class SpyLogger extends AbstractLogger
{
    /** @var array<int, array{level: mixed, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function has(string $level): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                return true;
            }
        }

        return false;
    }
}
