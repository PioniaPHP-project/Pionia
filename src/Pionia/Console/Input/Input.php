<?php

namespace Pionia\Console\Input;

abstract class Input implements InputInterface
{
    protected InputDefinition $definition;

    /** @var array<string, mixed> */
    protected array $arguments = [];

    /** @var array<string, mixed> */
    protected array $options = [];

    protected bool $interactive = true;

    public function bind(InputDefinition $definition): void
    {
        $this->definition = $definition;
        $this->parse();
        $this->validate();
    }

    abstract protected function parse(): void;

    public function validate(): void
    {
        $missing = [];
        foreach ($this->definition->getArguments() as $name => $argument) {
            if ($argument->isRequired() && !array_key_exists($name, $this->arguments)) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException(sprintf('Not enough arguments (missing: "%s").', implode('", "', $missing)));
        }
    }

    public function getArgument(int|string $name): mixed
    {
        if (is_int($name)) {
            $names = array_keys($this->definition->getArguments());
            $name = $names[$name] ?? throw new \InvalidArgumentException(sprintf('Argument index "%d" does not exist.', $name));
        }

        if (!array_key_exists($name, $this->arguments)) {
            if ($this->definition->hasArgument($name) && $this->definition->getArgument($name)->isOptional()) {
                return $this->definition->getArgument($name)->getDefault();
            }

            throw new \InvalidArgumentException(sprintf('Argument "%s" does not exist.', $name));
        }

        return $this->arguments[$name];
    }

    public function hasArgument(int|string $name): bool
    {
        if (is_int($name)) {
            return array_key_exists($name, array_values($this->definition->getArguments()));
        }

        return array_key_exists($name, $this->arguments) || $this->definition->hasArgument($name);
    }

    public function getArguments(): array
    {
        $values = [];
        foreach ($this->definition->getArguments() as $name => $argument) {
            $values[$name] = $this->arguments[$name] ?? $argument->getDefault();
        }

        return $values;
    }

    public function getOption(string $name): mixed
    {
        $name = ltrim($name, '-');

        if (!array_key_exists($name, $this->options)) {
            if ($this->definition->hasOption($name)) {
                return $this->definition->getOption($name)->getDefault();
            }

            throw new \InvalidArgumentException(sprintf('Option "%s" does not exist.', $name));
        }

        return $this->options[$name];
    }

    public function hasOption(string $name): bool
    {
        $name = ltrim($name, '-');

        return array_key_exists($name, $this->options) || $this->definition->hasOption($name);
    }

    public function getOptions(): array
    {
        $values = [];
        foreach ($this->definition->getOptions() as $name => $option) {
            $values[$name] = $this->options[$name] ?? $option->getDefault();
        }

        return $values;
    }

    public function isInteractive(): bool
    {
        return $this->interactive;
    }

    public function setInteractive(bool $interactive): void
    {
        $this->interactive = $interactive;
    }

    public function getParameterOption(string $name, mixed $default = false, bool $onlyParams = false): mixed
    {
        $name = ltrim($name, '-');

        if (array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        return $default;
    }

    public function getFirstArgument(): ?string
    {
        foreach ($this->definition->getArguments() as $name => $argument) {
            if (array_key_exists($name, $this->arguments)) {
                $value = $this->arguments[$name];

                return is_array($value) ? (string) ($value[0] ?? null) : (string) $value;
            }
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     */
    protected function parseTokens(array $tokens): void
    {
        $this->arguments = [];
        $this->options = [];
        $argNames = array_keys($this->definition->getArguments());
        $argIndex = 0;
        $currentArrayArg = null;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if ($token === '--') {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    $this->pushArgument($argNames, $argIndex, $tokens[$j], $currentArrayArg);
                }

                break;
            }

            if (str_starts_with($token, '--')) {
                $this->parseLongOption(substr($token, 2), $tokens, $i);

                continue;
            }

            if (str_starts_with($token, '-') && $token !== '-') {
                $this->parseShortOption(substr($token, 1), $tokens, $i);

                continue;
            }

            $this->pushArgument($argNames, $argIndex, $token, $currentArrayArg);
        }
    }

    /**
     * @param list<string> $argNames
     */
    private function pushArgument(array $argNames, int &$argIndex, string $value, ?string &$currentArrayArg): void
    {
        if ($argIndex >= count($argNames)) {
            throw new \RuntimeException('Too many arguments.');
        }

        $name = $argNames[$argIndex];
        $argument = $this->definition->getArgument($name);

        if ($argument->isArray()) {
            $this->arguments[$name] ??= [];
            $this->arguments[$name][] = $value;
            $currentArrayArg = $name;

            return;
        }

        if (array_key_exists($name, $this->arguments)) {
            throw new \RuntimeException(sprintf('Too many arguments, expected argument "%s".', $name));
        }

        $this->arguments[$name] = $value;
        $argIndex++;
    }

    /**
     * @param list<string> $tokens
     */
    private function parseLongOption(string $token, array $tokens, int &$index): void
    {
        if (str_contains($token, '=')) {
            [$name, $value] = explode('=', $token, 2);
            if (!$this->definition->hasOption($name)) {
                return;
            }
            $this->setOptionValue($name, $value);

            return;
        }

        if (!$this->definition->hasOption($name = $token)) {
            return;
        }

        $option = $this->definition->getOption($name);
        if (!$option->acceptValue()) {
            $this->options[$name] = true;

            return;
        }

        $value = $tokens[$index + 1] ?? null;
        if ($value === null || str_starts_with($value, '-')) {
            if ($option->isValueRequired()) {
                throw new \RuntimeException(sprintf('Option "--%s" requires a value.', $name));
            }

            $this->options[$name] = $option->getDefault() ?? true;
        } else {
            $this->setOptionValue($name, $value);
            $index++;
        }
    }

    /**
     * @param list<string> $tokens
     */
    private function parseShortOption(string $token, array $tokens, int &$index): void
    {
        $len = strlen($token);
        for ($i = 0; $i < $len; $i++) {
            $shortcut = $token[$i];
            if (!$this->definition->hasShortcut($shortcut)) {
                continue;
            }

            $option = $this->definition->getOptionForShortcut($shortcut);
            $name = $option->getName();

            if ($option->acceptValue()) {
                $value = $i === $len - 1 ? ($tokens[$index + 1] ?? null) : substr($token, $i + 1);
                if ($value === null || ($i === $len - 1 && str_starts_with((string) $value, '-'))) {
                    throw new \RuntimeException(sprintf('Option "-%s" requires a value.', $shortcut));
                }

                $this->setOptionValue($name, (string) $value);
                if ($i === $len - 1 && !str_starts_with((string) $value, '-')) {
                    $index++;
                }

                return;
            }

            $this->options[$name] = true;
        }
    }

    private function setOptionValue(string $name, string $value): void
    {
        $option = $this->definition->getOption($name);

        if ($option->isArray()) {
            $this->options[$name] ??= [];
            $this->options[$name][] = $value;

            return;
        }

        $this->options[$name] = $value;
    }
}
