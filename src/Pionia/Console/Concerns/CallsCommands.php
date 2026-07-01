<?php

namespace Pionia\Console\Concerns;

use Pionia\Console\AbstractCommand;
use Pionia\Console\Input\ArrayInput;
use Pionia\Console\Output\NullOutput;
use Pionia\Console\Output\OutputInterface;

trait CallsCommands
{
    abstract protected function resolveCommand(AbstractCommand|string $command): AbstractCommand;

    public function call(AbstractCommand|string $command, array $arguments = []): int
    {
        return $this->runCommand($command, $arguments, $this->output);
    }

    public function callSilent($command, array $arguments = []): int
    {
        return $this->runCommand($command, $arguments, new NullOutput());
    }

    public function callSilently(AbstractCommand|string $command, array $arguments = []): int
    {
        return $this->callSilent($command, $arguments);
    }

    protected function runCommand($command, array $arguments, OutputInterface $output): int
    {
        $arguments['command'] = is_string($command) ? $command : $command->getName();

        return $this->resolveCommand($command)->run(
            $this->createInputFromArguments($arguments),
            $output,
        );
    }

    protected function createInputFromArguments(array $arguments): ArrayInput
    {
        return tap(new ArrayInput(array_merge($this->context(), $arguments)), function ($input) {
            if ($input->getParameterOption('--no-interaction')) {
                $input->setInteractive(false);
            }
        });
    }

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
