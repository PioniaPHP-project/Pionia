<?php

namespace Pionia\CLI;

use Pionia\core\Pionia;
use Pionia\database\Connection;
use Porm\core\Database;
use Symfony\Component\Console\Command\Command;

/**
 * This is the base command class, it extends the Symfony console command class and provides some helper methods
 * that can be used in all commands. All commands should extend this class.
 * */
class BaseCommand extends Command
{

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
    }

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
     * @return Database
     *
     * @throws \Exception
     * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
     *
     */
    protected static function connection(string | null $db = null): Database
    {
        return Connection::use($db);
    }
}
