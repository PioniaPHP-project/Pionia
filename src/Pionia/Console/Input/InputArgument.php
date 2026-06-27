<?php

namespace Pionia\Console\Input;

final class InputArgument
{
    public const REQUIRED = 1;

    public const OPTIONAL = 2;

    public const IS_ARRAY = 4;

    public function __construct(
        private readonly string $name,
        private readonly int $mode = self::REQUIRED,
        private readonly string $description = '',
        mixed $default = null,
    ) {
        if ($default !== null && !$this->isOptional()) {
            throw new \InvalidArgumentException(sprintf('Argument "%s" cannot have a default value.', $name));
        }

        $this->default = $default;
    }

    private mixed $default = null;

    public function getName(): string
    {
        return $this->name;
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

    public function isRequired(): bool
    {
        return $this->mode === self::REQUIRED || ($this->mode & self::IS_ARRAY) === self::IS_ARRAY;
    }

    public function isOptional(): bool
    {
        return ($this->mode & self::OPTIONAL) === self::OPTIONAL;
    }

    public function isArray(): bool
    {
        return ($this->mode & self::IS_ARRAY) === self::IS_ARRAY;
    }
}
