<?php

namespace Pionia\Http\Routing;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Named collection of application routes.
 *
 * @implements IteratorAggregate<string, RouteDefinition>
 */
class RouteTable implements Countable, IteratorAggregate
{
    /** @var array<string, RouteDefinition> */
    private array $routes = [];

    public function add(string $name, RouteDefinition $route): static
    {
        $this->routes[$name] = $route;

        return $this;
    }

    public function addCollection(self $collection): static
    {
        foreach ($collection->all() as $name => $route) {
            $this->routes[$name] = $route;
        }

        return $this;
    }

    public function get(string $name): ?RouteDefinition
    {
        return $this->routes[$name] ?? null;
    }

    /**
     * @return array<string, RouteDefinition>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    public function getIterator(): Traversable
    {
        yield from $this->routes;
    }
}
