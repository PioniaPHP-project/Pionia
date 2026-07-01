<?php

namespace Pionia\Console;

use Pionia\Console\Input\InputDefinition;
use Pionia\Console\Input\InputInterface;
use Pionia\Console\Input\InputOption;
use Pionia\Console\Output\OutputInterface;

abstract class AbstractCommand
{
    public const SUCCESS = 0;

    public const FAILURE = 1;

    private ?Application $application = null;

    private string $name;

    /** @var list<string> */
    private array $aliases = [];

    private string $description = '';

    private string $help = '';

    private bool $hidden = false;

    private InputDefinition $definition;

    private bool $ignoreValidationErrors = false;

    public function __construct(?string $name = null)
    {
        $this->definition = new InputDefinition();
        $this->configure();

        if ($name !== null) {
            $this->setName($name);
        }
    }

    protected function configure(): void
    {
    }

    public function setApplication(?Application $application): void
    {
        $this->application = $application;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name ?? static::class;
    }

    /**
     * @param list<string> $aliases
     */
    public function setAliases(array $aliases): static
    {
        $this->aliases = $aliases;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public static function getDefaultDescription(): string
    {
        return '';
    }

    public function setHelp(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function getHelp(): string
    {
        return $this->help;
    }

    public function setHidden(bool $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function getDefinition(): InputDefinition
    {
        return $this->definition;
    }

    public function setDefinition(InputDefinition $definition): static
    {
        $this->definition = $definition;

        return $this;
    }

    public function addArgument(string $name, ?int $mode = null, string $description = '', mixed $default = null): static
    {
        $this->definition->addArgument(new Input\InputArgument(
            $name,
            $mode ?? Input\InputArgument::REQUIRED,
            $description,
            $default,
        ));

        return $this;
    }

    public function addOption(
        string $name,
        string|array|null $shortcut = null,
        ?int $mode = null,
        string $description = '',
        mixed $default = null,
    ): static {
        $this->definition->addOption(new InputOption(
            $name,
            $shortcut,
            $mode ?? InputOption::VALUE_NONE,
            $description,
            $default,
        ));

        return $this;
    }

    public function mergeApplicationDefinition(bool $mergeArgs = true): void
    {
        $applicationDefinition = $this->application?->getDefinition() ?? new InputDefinition();
        $commandDefinition = $this->definition;
        $this->definition = new InputDefinition();

        if ($mergeArgs) {
            foreach ($applicationDefinition->getArguments() as $argument) {
                $this->definition->addArgument($argument);
            }
            foreach ($commandDefinition->getArguments() as $argument) {
                $this->definition->addArgument($argument);
            }
        } else {
            foreach ($commandDefinition->getArguments() as $argument) {
                $this->definition->addArgument($argument);
            }
        }

        foreach ($applicationDefinition->getOptions() as $option) {
            $this->definition->addOption($option);
        }

        foreach ($commandDefinition->getOptions() as $option) {
            if (!$this->definition->hasOption($option->getName())) {
                $this->definition->addOption($option);
            }
        }
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->mergeApplicationDefinition();

        try {
            $input->bind($this->definition);
        } catch (\Throwable $e) {
            if (!$this->ignoreValidationErrors) {
                throw $e;
            }
        }

        return $this->execute($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return self::SUCCESS;
    }
}
