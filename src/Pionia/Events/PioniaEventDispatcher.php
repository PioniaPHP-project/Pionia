<?php

namespace Pionia\Events;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Lightweight PSR-14 event dispatcher (Symfony EventDispatcher replacement).
 */
final class PioniaEventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, list<array{0: callable, 1: int}>> */
    private array $listeners = [];

    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][] = [$listener, $priority];
    }

    /**
     * @param object $event
     */
    public function dispatch(object $event, ?string $eventName = null): object
    {
        $name = $eventName ?? $this->resolveEventName($event);

        foreach ($this->sortedListeners($name) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }

    /**
     * @return list<callable>
     */
    private function sortedListeners(string $eventName): array
    {
        $listeners = $this->listeners[$eventName] ?? [];

        if ($listeners === []) {
            return [];
        }

        usort($listeners, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

        return array_map(static fn (array $entry): callable => $entry[0], $listeners);
    }

    private function resolveEventName(object $event): string
    {
        if ($event instanceof Event) {
            return $event::name();
        }

        return $event::class;
    }
}
