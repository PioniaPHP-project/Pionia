<?php

namespace Pionia\Http\Moonlight;

/**
 * Resolves Moonlight service maps from the application container.
 */
final class MoonlightRegistry
{
    /**
     * @return array<string, mixed>
     */
    public static function resolve(?string $switch = null): array
    {
        if ($switch !== null && $switch !== '') {
            $registry = services(self::controllerKey($switch));

            if ($registry !== []) {
                return is_array($registry) ? $registry : [];
            }
        }

        return self::mergeAll();
    }

    /**
     * @return callable(): array<string, mixed>
     */
    public static function callable(?string $switch = null): callable
    {
        return static fn (): array => self::resolve($switch);
    }

    /**
     * @return array<string, mixed>
     */
    private static function mergeAll(): array
    {
        $merged = [];

        foreach (services() as $map) {
            if (!is_array($map)) {
                continue;
            }

            foreach ($map as $alias => $class) {
                $merged[$alias] = $class;
            }
        }

        return $merged;
    }

    private static function controllerKey(string $switch): string
    {
        if (str_contains($switch, '::')) {
            return $switch;
        }

        if (class_exists($switch)) {
            return $switch . '::processor';
        }

        return $switch;
    }
}
