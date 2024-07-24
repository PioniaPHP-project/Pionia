<?php

namespace Pionia\Command;

use Exception;
use Pionia\Core\Helpers\Utilities;
use Pionia\Core\Pionia;
use Pionia\Exceptions\CommandException;
use Pionia\Logging\PioniaLogger;

/**
 * This is the command interface, it is the entry point for all commands in the framework
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class CommandInterface extends Pionia
{
    /**
     * These are the core commands that are available in the framework
     * @var array|string[] $commands
     */
    private array $commands = [
        'Pionia\Command\Commands\StartServer',
        'Pionia\Command\Commands\CreateService',
        'Pionia\Command\Commands\CreateAuthenticationBackend',
        'Pionia\Command\Commands\CreateMiddleware',
    ];

    /**
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }


    /**
     * Checks if a command is valid and then adds it to the in-built commands stack
     * @throws CommandException
     */
    private function addCommand(string $command): array
    {
        $check = Utilities::extends($command, 'Pionia\Command\BaseCommand');
        if ($check === 'NO_CLASS'){
            logger->info("Class not found");
            throw new CommandException("Command {$command} class not found");
        } elseif ($check === 'DOES_NOT'){
            throw new CommandException("Command {$command} must extend Pionia\Command\BaseCommand");
        }
        $this->commands[]= $command;
        return $this->commands;
    }

    /**
     * @throws Exception
     */
    private function run(): ConsoleApplication
    {
        $app = new ConsoleApplication();
        foreach ($this->commands as $command){
            $app->add(new $command());
        }
        $app->run();
        return $app;
    }

    /**
     * Sets a new Pionia instance, merges all commands declared in settings.ini with the inbuilt commands.
     *
     * @throws Exception
     */
    public static function setUp(): ConsoleApplication
    {
        if (!defined('logger')){
            define('logger', PioniaLogger::init());
        }
        if (!defined("pionia")){
            define('pionia', Pionia::boot());
        }
        $app = new self();
        $otherCommands = $app->getSetting('commands');
        if ($otherCommands){
            $actual = array_values($otherCommands);
            foreach ($actual as $command){
                $app->addCommand($command);
            }
        }
        return $app->run();
    }
}
