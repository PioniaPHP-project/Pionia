<?php

namespace Pionia\Events;

use AllowDynamicProperties;
use Pionia\Utils\Support;
use Symfony\Contracts\EventDispatcher\Event as SymfonyEvent;

#[AllowDynamicProperties]
class Event extends SymfonyEvent
{
    public function __construct(...$args)
    {
        foreach ($args as $key => $value) {
            $this->__set($key, $value);
        }
    }

    public function __set(string $name, $value): void
    {
        $this->{$name} = $value;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public static function name(): string
    {
        return Support::formatter()->tableize((new \ReflectionClass(static::class))->getShortName());
    }

}
