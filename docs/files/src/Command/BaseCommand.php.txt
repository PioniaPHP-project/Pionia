<?php

namespace Pionia\Command;

use Exception;
use Pionia\Core\Pionia;
use Pionia\Database\Connection;
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
     * Returns the current database connection
     *
     * @param string|null $db
     * @return Database
     *
     * @throws Exception
     * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
     *
     */
    protected static function connection(string | null $db = null): Database
    {
        return Connection::use($db);
    }
}
