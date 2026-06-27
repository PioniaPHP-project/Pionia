<?php

namespace Pionia\Console\Input;

interface InputInterface
{
    public const ARGUMENT_NONE = 1;

    public const ARGUMENT_REQUIRED = 2;

    public const ARGUMENT_OPTIONAL = 4;

    public function bind(InputDefinition $definition): void;

    public function validate(): void;

    public function getArgument(int|string $name): mixed;

    public function hasArgument(int|string $name): bool;

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array;

    public function getOption(string $name): mixed;

    public function hasOption(string $name): bool;

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    public function isInteractive(): bool;

    public function setInteractive(bool $interactive): void;

    public function getParameterOption(string $name, mixed $default = false, bool $onlyParams = false): mixed;

    public function getFirstArgument(): ?string;
}
