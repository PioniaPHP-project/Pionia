<?php

namespace Pionia\command;

use Exception;
use Pionia\core\helpers\Utilities;
use Pionia\exceptions\CommandException;

/**
 * This is the command interface, it is the entry point for all commands in the framework
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class CommandInterface
{
    /**
     * These are the core commands that are available in the framework
     * @var array|string[] $commands
     */
    private static array $commands = [
        'Pionia\command\commands\StartServer',
        'Pionia\command\commands\CreateService',
        'Pionia\command\commands\CreateAuthenticationBackend',
    ];


    /**
     * @throws CommandException
     */
    public static function addCommand(string $command): array
    {
        if (Utilities::extends($command, 'Pionia\command\BaseCommand') === 'NO_CLASS'){
            throw new CommandException("Command {$command} class not found");
        }
        self::$commands[]= $command;
        return self::$commands;
    }

    /**
     * @throws Exception
     */
    public static function run(): ConsoleApplication
    {
        $app = new ConsoleApplication();
        foreach (self::$commands as $command){
            $app->add(new $command());
        }

        $app->run();
        return $app;
    }

    /**
     * @throws Exception
     */
    public static function setUp(): ConsoleApplication
    {
        return self::run();
    }
}
