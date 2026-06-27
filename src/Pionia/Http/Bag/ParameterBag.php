<?php

namespace Pionia\Http\Bag;

/**
 * Mutable string-keyed parameter store (query, post, attributes, cookies).
 */
class ParameterBag
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        private array $parameters = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->parameters;
    }

    public function keys(): array
    {
        return array_keys($this->parameters);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->parameters);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        if (!is_scalar($value) && $value !== null) {
            return $default;
        }

        return (string) $value;
    }

    public function set(string $key, mixed $value): static
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function add(array $parameters): static
    {
        foreach ($parameters as $key => $value) {
            $this->parameters[$key] = $value;
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function replace(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function remove(string $key): static
    {
        unset($this->parameters[$key]);

        return $this;
    }
}
