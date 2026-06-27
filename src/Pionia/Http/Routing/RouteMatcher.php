<?php

namespace Pionia\Http\Routing;

use Pionia\Http\Routing\Exception\MethodNotAllowedException;
use Pionia\Http\Routing\Exception\RouteNotFoundException;

/**
 * Matches request paths and methods against a route table.
 */
final class RouteMatcher
{
    /**
     * @var list<array{
     *     name: string,
     *     methods: list<string>,
     *     regex: string,
     *     defaults: array<string, mixed>,
     *     specificity: int
     * }>
     */
    private array $compiled = [];

    public function __construct(RouteTable $table)
    {
        $this->compile($table);
    }

    /**
     * @return array<string, mixed>
     */
    public function match(string $pathInfo, string $method): array
    {
        $path = $pathInfo === '' ? '/' : $pathInfo;
        $method = strtoupper($method);
        /** @var list<string> $allowedMethods */
        $allowedMethods = [];

        foreach ($this->compiled as $entry) {
            if (!preg_match($entry['regex'], $path, $matches)) {
                continue;
            }

            if (!$this->methodMatches($entry['methods'], $method)) {
                array_push($allowedMethods, ...$entry['methods']);

                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                $params[$key] = $value;
            }

            return array_merge($entry['defaults'], $params, ['_route' => $entry['name']]);
        }

        if ($allowedMethods !== []) {
            throw new MethodNotAllowedException(array_values(array_unique($allowedMethods)));
        }

        throw new RouteNotFoundException(sprintf('No route found for "%s" (%s).', $path, $method));
    }

    private function compile(RouteTable $table): void
    {
        foreach ($table->all() as $name => $route) {
            $this->compiled[] = [
                'name' => $name,
                'methods' => array_map('strtoupper', $route->methods()),
                'regex' => $this->compilePath($route->path(), $route->requirements()),
                'defaults' => $route->defaults(),
                'specificity' => $this->specificity($route->path()),
            ];
        }

        usort(
            $this->compiled,
            static fn (array $a, array $b): int => $b['specificity'] <=> $a['specificity'],
        );
    }

    private function compilePath(string $path, array $requirements): string
    {
        $pattern = '';
        $parts = preg_split('#(\{[^}]+\})#', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($parts as $part) {
            if (preg_match('#^\{([^}]+)\}$#', $part, $matches)) {
                $param = $matches[1];
                $requirement = $requirements[$param] ?? '[^/]+';
                $pattern .= '(?P<' . $param . '>' . $requirement . ')';

                continue;
            }

            $pattern .= preg_quote($part, '#');
        }

        return '#^' . $pattern . '$#';
    }

    private function specificity(string $path): int
    {
        $literalLength = strlen(preg_replace('#\{[^}]+\}#', '', $path) ?: $path);
        $paramCount = preg_match_all('#\{[^}]+\}#', $path);

        return ($literalLength * 10) - ($paramCount * 5);
    }

    /**
     * @param list<string> $routeMethods
     */
    private function methodMatches(array $routeMethods, string $method): bool
    {
        if ($routeMethods === []) {
            return true;
        }

        return in_array($method, $routeMethods, true);
    }
}
