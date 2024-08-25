<?php

namespace Pionia\Pionia\Console;

use AllowDynamicProperties;
use Closure;
use Exception;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Console\Concerns\CallsCommands;
use Pionia\Pionia\Console\Concerns\HasParameters;
use Pionia\Pionia\Console\Concerns\InteractsWithIO;
use Pionia\Pionia\Contracts\IsolatableContract;
use Pionia\Pionia\Utils\Microable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AllowDynamicProperties]
class BaseCommand extends Command
{
    use Microable,
        InteractsWithIO,
        HasParameters,
        CallsCommands;

    protected bool $isolated = false;

    protected string $description;

    protected string $signature;

    protected string $help;

    protected string $name;

    protected array $aliases;

    protected bool $hidden = false;

    private ?PioniaApplication $app;

    protected function resolveCommand($command): Command
    {
        if (is_string($command)) {
            if (! class_exists($command)) {
                return $this->getApplication()->find($command);
            }
            $command = $this->app->getSilently($command);
        }
        if ($command instanceof Command) {
            $command->setApplication($this->getApplication());
        }
        if ($command instanceof self) {
            $command->setApp($this->getApp());
        }
        return $command;
    }

    public function getApp(): ?PioniaApplication
    {
        return $this->app;
    }

    /**
     * @throws Throwable
     */
    public function callCommand(array $arguments, ?Closure $postRun = null): int
    {
        $cmd = new ArrayInput($arguments);
        $returnCode =  $this->getApplication()->doRun($cmd, $this->output);
        if ($postRun) {
            $postRun($returnCode, $this->output);
        }
        $this->setOutput($this->output);
        return $returnCode;
    }

    public function __construct(?PioniaApplication $app=null, ?string $name = null)
    {
        // We will go ahead and set the name, description, and parameters on console
        // commands just to make things a little easier on the developer. This is
        // so they don't have to all be manually specified in the constructors.
        $this->app = $app;
        $this->name = $name;

        parent::__construct($this->name);

        // Once we have constructed the command, we'll set the description and other
        // related properties of the command. If a signature wasn't used to build
        // the command we'll set the arguments and the options on this command.
        if (! isset($this->description)) {
            $this->setDescription((string) static::getDefaultDescription());
        } else {
            $this->setDescription($this->description);
        }

        $this->setHelp($this->help);

        $this->setHidden($this->isHidden());

        if (isset($this->aliases)) {
            $this->setAliases($this->aliases);
        }

        if (! isset($this->signature)) {
            $this->specifyParameters();
        }
    }



    private function setApp(?PioniaApplication $app): void
    {
        $this->app = $app;
    }

    /**
     * Execute the console command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;

        $this->output = $output instanceof OutputStyle ? $output : $this->app->context->make(
            OutputStyle::class, ['input' => $input, 'output' => $output]
        );

        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        try {
            return (int) call_user_func([$this, $method]);
        } catch (Exception $e) {
            $this->app->logger?->error($e->getMessage());

            return static::FAILURE;
        }
    }

}
