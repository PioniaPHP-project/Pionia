<?php

namespace Pionia\Console\Concerns;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

trait CallsCommands
{
    /**
     * Resolve the console command instance for the given command.
     *
     * @param string|Command $command
     * @return Command
     */
    abstract protected function resolveCommand(Command|string $command): Command;

    /**
     * Call another console command.
     *
     * @param string|Command $command
     * @param  array  $arguments
     * @return int
     */
    public function call(Command|string $command, array $arguments = []): int
    {
        return $this->runCommand($command, $arguments, $this->output);
    }

    /**
     * Call another console command without output.
     *
     * @param  Command|string  $command
     * @param  array  $arguments
     * @return int
     */
    public function callSilent($command, array $arguments = []): int
    {
        return $this->runCommand($command, $arguments, new NullOutput);
    }

    /**
     * Call another console command without output.
     *
     * @param string|Command $command
     * @param  array  $arguments
     * @return int
     */
    public function callSilently(Command|string $command, array $arguments = []): int
    {
        return $this->callSilent($command, $arguments);
    }

    /**
     * Run the given the console command.
     *
     * @param Command|string $command
     * @param array $arguments
     * @param OutputInterface $output
     * @return int
     * @throws ExceptionInterface
     */
    protected function runCommand($command, array $arguments, OutputInterface $output): int
    {
        $arguments['command'] = $command;

        $result = $this->resolveCommand($command)->run(
            $this->createInputFromArguments($arguments), $output
        );

        $this->restorePrompts();

        return $result;
    }

    /**
     * Create an input instance from the given arguments.
     *
     * @param  array  $arguments
     * @return ArrayInput
     */
    protected function createInputFromArguments(array $arguments): ArrayInput
    {
        return tap(new ArrayInput(array_merge($this->context(), $arguments)), function ($input) {
            if ($input->getParameterOption('--no-interaction')) {
                $input->setInteractive(false);
            }
        });
    }

    /**
     * Get all of the context passed to the command.
     *
     * @return array
     */
    protected function context(): array
    {
        return arr($this->option())->only([
            'ansi',
            'no-ansi',
            'no-interaction',
            'quiet',
            'verbose',
        ])->filter()->mapWithKeys(function ($value, $key) {
            return ["--{$key}" => $value];
        })->all();
    }
}
