<?php

namespace Pionia\Console;

use Pionia\Console\Input\ArgvInput;
use Pionia\Console\Input\ArrayInput;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputDefinition;
use Pionia\Console\Input\InputInterface;
use Pionia\Console\Input\InputOption;
use Pionia\Console\Output\ConsoleOutput;
use Pionia\Console\Output\OutputInterface;

class Application
{
    /** @var array<string, Command> */
    private array $commands = [];

    /** @var array<string, Command> */
    private array $aliases = [];

    private bool $autoExit = true;

    private InputDefinition $definition;

    private bool $booted = false;

    public function __construct(
        private string $name = 'Pionia',
        private string $version = '1.0.0',
    ) {
        $this->definition = new InputDefinition([
            new InputArgument('command', InputArgument::OPTIONAL, 'The command to execute', 'list'),
            new InputOption('help', 'h', InputOption::VALUE_NONE, 'Display help for the given command'),
            new InputOption('quiet', 'q', InputOption::VALUE_NONE, 'Do not output any message'),
            new InputOption('verbose', 'v|vv|vvv', InputOption::VALUE_NONE, 'Increase verbosity'),
            new InputOption('version', 'V', InputOption::VALUE_NONE, 'Display this application version'),
            new InputOption('ansi', null, InputOption::VALUE_NONE, 'Force ANSI output'),
            new InputOption('no-ansi', null, InputOption::VALUE_NONE, 'Disable ANSI output'),
            new InputOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Do not ask interactive questions'),
        ]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function setAutoExit(bool $autoExit): static
    {
        $this->autoExit = $autoExit;

        return $this;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function getDefinition(): InputDefinition
    {
        return $this->definition;
    }

    public function add(Command $command): ?Command
    {
        $command->setApplication($this);
        $this->commands[$command->getName()] = $command;

        foreach ($command->getAliases() as $alias) {
            $this->aliases[$alias] = $command;
        }

        return $command;
    }

    public function find(string $name): Command
    {
        if (isset($this->commands[$name])) {
            return $this->commands[$name];
        }

        if (isset($this->aliases[$name])) {
            return $this->aliases[$name];
        }

        $matches = [];
        foreach (array_keys($this->commands + $this->aliases) as $registered) {
            if (str_starts_with($registered, $name)) {
                $matches[] = $registered;
            }
        }

        if (count($matches) === 1) {
            return $this->find($matches[0]);
        }

        if ($matches !== []) {
            throw new \InvalidArgumentException(sprintf('Command "%s" is ambiguous (%s).', $name, implode(', ', $matches)));
        }

        throw new \InvalidArgumentException(sprintf('Command "%s" is not defined.', $name));
    }

    /**
     * @return array<string, Command>
     */
    public function all(): array
    {
        return $this->commands;
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $input ??= new ArgvInput();
        $output ??= new ConsoleOutput();

        $exitCode = $this->doRun($input, $output);

        if ($this->autoExit && class_exists(\Composer\Autoload\ClassLoader::class)) {
            // allow tests to capture exit code without exiting process
        }

        return $exitCode;
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        try {
            $input->bind($this->definition);
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $this->configureIO($input, $output);

        $commandName = null;
        if ($input instanceof ArrayInput) {
            $commandName = $input->getCommandName();
        }

        if ($commandName === null) {
            try {
                $commandName = (string) $input->getArgument('command');
            } catch (\Throwable) {
                $commandName = 'list';
            }
        }

        if ($input->getOption('help')) {
            if (!isset($this->commands['help'])) {
                $this->registerBuiltinCommands();
            }

            return $this->find('help')->run(new ArrayInput(['command' => 'help', 'command_name' => $commandName]), $output);
        }

        if ($input->getOption('version')) {
            $output->writeln($this->name . ' ' . $this->version);

            return Command::SUCCESS;
        }

        if (!isset($this->commands[$commandName]) && !isset($this->aliases[$commandName])) {
            if ($commandName === '' || $commandName === 'list') {
                $this->registerBuiltinCommands();

                return $this->find('list')->run(new ArrayInput(['command' => 'list']), $output);
            }
        }

        $this->registerBuiltinCommands();

        try {
            $command = $this->find($commandName);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        return $command->run($input, $output);
    }

    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getOption('quiet')) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        } elseif ($input->getOption('verbose')) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        if ($input->getOption('no-ansi')) {
            $output->setDecorated(false);
        } elseif ($input->getOption('ansi')) {
            $output->setDecorated(true);
        }

        if ($input->getOption('no-interaction')) {
            $input->setInteractive(false);
        }
    }

    protected function registerBuiltinCommands(): void
    {
        if (isset($this->commands['list'])) {
            return;
        }

        $this->add(new Commands\ListCommand());
        $this->add(new Commands\HelpCommand());
        $this->add(new Commands\ShellCommand());
    }

    public function markBooted(): void
    {
        $this->booted = true;
    }
}
