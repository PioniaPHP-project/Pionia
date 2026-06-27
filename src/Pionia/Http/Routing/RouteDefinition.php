<?php

namespace Pionia\Http\Routing;

/**
 * A single HTTP route (path, methods, defaults, parameter requirements).
 */
final class RouteDefinition
{
    /**
     * @param array<string, mixed> $defaults
     * @param array<string, string> $requirements
     * @param list<string> $methods
     */
    public function __construct(
        private readonly string $path,
        private readonly array $defaults = [],
        private readonly array $requirements = [],
        private readonly array $methods = [],
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * @return array<string, string>
     */
    public function requirements(): array
    {
        return $this->requirements;
    }

    /**
     * @return list<string>
     */
    public function methods(): array
    {
        return $this->methods;
    }

    public function default(string $key, mixed $default = null): mixed
    {
        return $this->defaults[$key] ?? $default;
    }
}
