<?php

namespace Pionia\Console\Input;

final class InputOption
{
    public const VALUE_NONE = 1;

    public const VALUE_REQUIRED = 2;

    public const VALUE_OPTIONAL = 4;

    public const VALUE_IS_ARRAY = 8;

    public const VALUE_NEGATABLE = 16;

    public function __construct(
        private readonly string $name,
        private readonly string|array|null $shortcut = null,
        private readonly int $mode = self::VALUE_NONE,
        private readonly string $description = '',
        mixed $default = null,
    ) {
        if ($default !== null && !$this->acceptValue()) {
            throw new \InvalidArgumentException(sprintf('Option "%s" cannot have a default value.', $name));
        }

        $this->default = $default;
    }

    private mixed $default = null;

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function getShortcuts(): array
    {
        if ($this->shortcut === null || $this->shortcut === '') {
            return [];
        }

        if (is_array($this->shortcut)) {
            return $this->shortcut;
        }

        return preg_split('/\|/', (string) $this->shortcut) ?: [];
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function acceptValue(): bool
    {
        return $this->mode > self::VALUE_NONE;
    }

    public function isValueRequired(): bool
    {
        return $this->mode === self::VALUE_REQUIRED;
    }

    public function isArray(): bool
    {
        return ($this->mode & self::VALUE_IS_ARRAY) === self::VALUE_IS_ARRAY;
    }

    public function isNegatable(): bool
    {
        return ($this->mode & self::VALUE_NEGATABLE) === self::VALUE_NEGATABLE;
    }
}
