<?php

namespace Pionia\command;

use PDO;
use Pionia\core\Pionia;
use Pionia\database\Connector;
use Pionia\exceptions\DatabaseException;
use Symfony\Component\Console\Command\Command;

/**
 * This is the base command class, it extends the Symfony console command class and provides some helper methods
 * that can be used in all commands. All commands should extend this class.
 * */
class BaseCommand extends Command
{

    /**
     * Return the base app, via this, you can access all the app settings, and current app environment
     *
     * @return Pionia
     */
    protected static function base(): Pionia
    {
        return new Pionia();
    }

    protected function getServerSettings(): array
    {
        $settings =  Pionia::getSetting("server");
        if ($settings && is_array($settings)) {
            return $settings;
        }
        return [];
    }

    /**
     * Returns the current database connection
     *
     * @param string|null $db
     * @return PDO
     *
     * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
     * */
    protected static function connection(string | null $db = null): PDO
    {
        return Connector::connect($db);
    }
}
