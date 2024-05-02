<?php

namespace JetPhp\Command;

use Exception;
use JetPhp\Core\Helpers\Utilities;
use JetPhp\Exeptions\CommandException;


class CommandInterface
{
    /**
     * These are the core commands that are available in the framework
     * @var array|string[] $commands
     */
    private static array $commands = [
        'JetPhp\Command\Core\StartServer',
    ];


    /**
     * @throws CommandException
     */
    public static function addCommand(string $command): array
    {
        if (Utilities::extends($command, 'JetPhp\Command\BaseCommand') === 'NO_CLASS'){
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
