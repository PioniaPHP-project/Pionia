<?php

namespace Pionia\Events;

use AllowDynamicProperties;
use Pionia\Utils\Support;
use Psr\EventDispatcher\StoppableEventInterface;

#[AllowDynamicProperties]
class Event implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(...$args)
    {
        foreach ($args as $key => $value) {
            $this->__set($key, $value);
        }
    }

    public function __set(string $name, mixed $value): void
    {
        $this->{$name} = $value;
    }

    public static function name(): string
    {
        return Support::formatter()->tableize((new \ReflectionClass(static::class))->getShortName());
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
