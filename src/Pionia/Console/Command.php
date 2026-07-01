<?php

namespace Pionia\Console;

use AllowDynamicProperties;
use Closure;
use Pionia\Base\WebApplication;
use Pionia\Console\Concerns\CallsCommands;
use Pionia\Console\Concerns\HasParameters;
use Pionia\Console\Concerns\InteractsWithIO;
use Pionia\Console\Input\ArgvInput;
use Pionia\Console\Input\ArrayInput;
use Pionia\Console\Input\InputInterface;
use Pionia\Console\Output\OutputInterface;
use Pionia\Contracts\ApplicationContract;
use Pionia\Utils\Microable;
use Pionia\Utils\Support;
use Throwable;

#[AllowDynamicProperties]
class Command extends AbstractCommand
{
    use Microable,
        InteractsWithIO,
        HasParameters,
        CallsCommands;

    protected string $description;

    protected string $signature;

    protected string $help;

    protected string $name;

    protected array $aliases;

    protected bool $hidden = false;

    private ?ApplicationContract $app;

    protected function resolveCommand($command): AbstractCommand
    {
        if (is_string($command)) {
            if (! class_exists($command)) {
                return $this->getApplication()->find($command);
            }
            $command = realm()->getSilently($command);
        }
        if ($command instanceof AbstractCommand) {
            $command->setApplication($this->getApplication());
        }
        if ($command instanceof self) {
            $command->setApp($this->getApp());
        }

        return $command;
    }

    public function getApp(): ?ApplicationContract
    {
        return $this->app;
    }

    /**
     * @throws Throwable
     */
    public function callCommand(array $arguments, ?Closure $postRun = null): int
    {
        $cmd = new ArrayInput($arguments);
        $returnCode = $this->getApplication()->doRun($cmd, $this->output);
        if ($postRun) {
            $postRun($returnCode, $this->output);
        }
        $this->setOutput($this->output);

        return $returnCode;
    }

    public function resolveCommandNameFromClassName(): string
    {
        if (!isset($this->name)) {
            $parts = explode('\\', static::class);
            $className = array_pop($parts);
            str_ireplace('Command', '', $className);

            return 'command:'.Support::singularize(Support::toSnakeCase($className));
        }

        return $this->name;
    }

    public function __construct(?ApplicationContract $app = null)
    {
        $this->app = $app;
        $this->name = $this->name ?? $this->resolveCommandNameFromClassName();
        parent::__construct($this->name);

        if (! isset($this->description)) {
            $this->setDescription((string) static::getDefaultDescription());
        } else {
            $this->setDescription($this->description);
        }

        $this->setHelp($this->help ?? '');

        $this->setHidden($this->hidden);

        if (isset($this->aliases)) {
            $this->setAliases($this->aliases);
        }

        if (isset($this->signature)) {
            [$name, $arguments, $options] = Parser::parse($this->signature);
            $this->setName($name);
            foreach ($arguments as $argument) {
                $this->getDefinition()->addArgument($argument);
            }
            foreach ($options as $option) {
                $this->getDefinition()->addOption($option);
            }
        } else {
            $this->specifyParameters();
        }
    }

    private function setApp(?WebApplication $app): void
    {
        $this->app = $app;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;

        $this->output = $output instanceof OutputStyle ? $output : realm()->make(
            OutputStyle::class,
            ['input' => $input, 'output' => $output],
        );

        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        try {
            return (int) call_user_func([$this, $method]);
        } catch (Throwable $e) {
            logger()?->error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            if (isset($this->output)) {
                $this->output->writeln('<error>' . $e->getMessage() . '</error>');
                $this->output->writeln('<comment>' . $e->getFile() . ':' . $e->getLine() . '</comment>');
            }

            return static::FAILURE;
        }
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->mergeApplicationDefinition(false);

        if ($input instanceof ArgvInput) {
            $input = $input->withTokens($this->stripCommandTokens($input->getTokens()));
        }

        $input->bind($this->getDefinition());

        return $this->execute($input, $output);
    }

    /**
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private function stripCommandTokens(array $tokens): array
    {
        if ($tokens === []) {
            return $tokens;
        }

        $names = array_merge([$this->getName()], $this->getAliases());

        if (in_array($tokens[0], $names, true)) {
            return array_slice($tokens, 1);
        }

        return $tokens;
    }
}
