<?php

namespace Pionia\Console\Input;

final class InputDefinition
{
    /** @var array<string, InputArgument> */
    private array $arguments = [];

    /** @var array<int, string> */
    private array $argumentPositions = [];

    /** @var array<string, InputOption> */
    private array $options = [];

    /** @var array<string, string> */
    private array $shortcutMap = [];

    private ?InputArgument $requiredCount = null;

    public function __construct(array $definition = [])
    {
        foreach ($definition as $item) {
            if ($item instanceof InputArgument) {
                $this->addArgument($item);
            } elseif ($item instanceof InputOption) {
                $this->addOption($item);
            }
        }
    }

    public function setDefinition(array $definition): void
    {
        $this->arguments = [];
        $this->argumentPositions = [];
        $this->options = [];
        $this->shortcutMap = [];

        foreach ($definition as $item) {
            if ($item instanceof InputArgument) {
                $this->addArgument($item);
            } elseif ($item instanceof InputOption) {
                $this->addOption($item);
            }
        }
    }

    public function addArgument(InputArgument $argument): void
    {
        if (isset($this->arguments[$argument->getName()])) {
            throw new \LogicException(sprintf('Argument "%s" already exists.', $argument->getName()));
        }

        if ($this->hasArrayArgument()) {
            throw new \LogicException('Cannot add arguments after an array argument.');
        }

        if ($argument->isRequired() && $this->hasOptionalArgument()) {
            throw new \LogicException('Cannot add a required argument after an optional one.');
        }

        $this->arguments[$argument->getName()] = $argument;
        $this->argumentPositions[] = $argument->getName();
    }

    public function addOption(InputOption $option): void
    {
        if (isset($this->options[$option->getName()])) {
            throw new \LogicException(sprintf('Option "%s" already exists.', $option->getName()));
        }

        $this->options[$option->getName()] = $option;

        foreach ($option->getShortcuts() as $shortcut) {
            if (isset($this->shortcutMap[$shortcut])) {
                throw new \LogicException(sprintf('Shortcut "-%s" is already used.', $shortcut));
            }

            $this->shortcutMap[$shortcut] = $option->getName();
        }
    }

    public function getArgument(string $name): InputArgument
    {
        if (!isset($this->arguments[$name])) {
            throw new \InvalidArgumentException(sprintf('Argument "%s" does not exist.', $name));
        }

        return $this->arguments[$name];
    }

    public function hasArgument(string $name): bool
    {
        return isset($this->arguments[$name]);
    }

    /**
     * @return array<string, InputArgument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getArgumentCount(): int
    {
        return count($this->arguments);
    }

    public function getOption(string $name): InputOption
    {
        if (!isset($this->options[$name])) {
            throw new \InvalidArgumentException(sprintf('Option "%s" does not exist.', $name));
        }

        return $this->options[$name];
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * @return array<string, InputOption>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function hasShortcut(string $name): bool
    {
        return isset($this->shortcutMap[$name]);
    }

    public function getOptionForShortcut(string $shortcut): InputOption
    {
        if (!isset($this->shortcutMap[$shortcut])) {
            throw new \InvalidArgumentException(sprintf('Option shortcut "%s" does not exist.', $shortcut));
        }

        return $this->options[$this->shortcutMap[$shortcut]];
    }

    public function merge(InputDefinition $definition): void
    {
        foreach ($definition->getOptions() as $option) {
            if (!$this->hasOption($option->getName())) {
                $this->addOption($option);
            }
        }

        foreach ($definition->getArguments() as $argument) {
            if (!$this->hasArgument($argument->getName())) {
                $this->addArgument($argument);
            }
        }
    }

    private function hasOptionalArgument(): bool
    {
        foreach ($this->arguments as $argument) {
            if ($argument->isOptional()) {
                return true;
            }
        }

        return false;
    }

    private function hasArrayArgument(): bool
    {
        foreach ($this->arguments as $argument) {
            if ($argument->isArray()) {
                return true;
            }
        }

        return false;
    }
}
