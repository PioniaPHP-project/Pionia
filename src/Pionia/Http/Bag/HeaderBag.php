<?php

namespace Pionia\Http\Bag;

/**
 * HTTP header bag with case-insensitive lookup.
 */
class HeaderBag
{
    /** @var array<string, list<string>> */
    private array $headers = [];

    /** @var array<string, string> */
    private array $cache = [];

    /**
     * @param array<string, list<string>|string> $headers
     */
    public function __construct(array $headers = [])
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists(strtolower($key), $this->cache);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $canonical = $this->cache[strtolower($key)] ?? null;
        if ($canonical === null) {
            return $default;
        }

        $values = $this->headers[$canonical] ?? [];

        return $values === [] ? $default : implode(', ', $values);
    }

    /**
     * @param list<string>|string $values
     */
    public function set(string $key, array|string $values): static
    {
        $canonical = $this->getCanonicalName($key);
        $normalized = is_array($values) ? array_values($values) : [$values];

        $this->headers[$canonical] = $normalized;
        $this->cache[strtolower($canonical)] = $canonical;

        return $this;
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, list<string>>
     */
    public function allPreserveCaseWithoutCookies(): array
    {
        $headers = [];

        foreach ($this->headers as $name => $values) {
            if (strcasecmp($name, 'Set-Cookie') === 0) {
                continue;
            }

            $headers[$name] = $values;
        }

        return $headers;
    }

    private function getCanonicalName(string $name): string
    {
        $lower = strtolower($name);

        return $this->cache[$lower] ?? $name;
    }
}
