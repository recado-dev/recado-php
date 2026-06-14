<?php

declare(strict_types=1);

namespace Mailer\Sdk\Tests\Mail\Support;

use Illuminate\Contracts\Events\Dispatcher;

/**
 * Minimal in-memory implementation of Laravel's event dispatcher contract for
 * the transport unit tests: it only records dispatched event objects.
 */
final class SpyDispatcher implements Dispatcher
{
    /** @var array<int, object|string> */
    public array $dispatched = [];

    /**
     * @return array<int, object>
     */
    public function ofType(string $class): array
    {
        return array_values(array_filter(
            $this->dispatched,
            static fn ($event): bool => $event instanceof $class,
        ));
    }

    public function listen($events, $listener = null): void
    {
    }

    public function hasListeners($eventName): bool
    {
        return false;
    }

    public function subscribe($subscriber): void
    {
    }

    public function until($event, $payload = [])
    {
        return null;
    }

    public function dispatch($event, $payload = [], $halt = false)
    {
        $this->dispatched[] = $event;

        return null;
    }

    public function push($event, $payload = []): void
    {
    }

    public function flush($event): void
    {
    }

    public function forget($event): void
    {
    }

    public function forgetPushed(): void
    {
    }
}
