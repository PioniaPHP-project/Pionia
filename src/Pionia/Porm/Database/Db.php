<?php

/**
 * PORM - CDatabase querying tool for pionia framework.
 *
 * This package can be used as is or with the Pionia Framework. Anyone can reproduce and update this as they see fit.
 *
 * @copyright 2024,  Pionia Project - Jet Ezra
 *
 * @author Jet Ezra
 * @version 1.0.0
 * @link https://pionia.netlify.app/
 * @license https://opensource.org/licenses/MIT
 *
 **/

namespace Pionia\Porm\Database;

use Exception;
use Pionia\Base\PioniaApplication;
use Pionia\Collections\Arrayable;
use Pionia\Porm\Core\Porm;
use Pionia\Porm\Driver\Connection;

/**
 * Provides a basis for other query builders to base on.
 */
class Db
{
    /**
     * The Pionia application instance
     */
    private PioniaApplication $application;

    /**
     * The connection to use
     * @var Connection
     */
    private Connection $connection;

    /**
     * The database query instance
     */
    private Porm $porm;

    /**
     * Setup the database connection
     */
    public function __construct(?PioniaApplication $application = null, Connection | null | string | array $connection = 'default')
    {
        if ($connection instanceof Connection) {
            $this->application = $connection->getApplication();
            $this->connection = $connection;
            $this->porm = new Porm($this->connection);
        } else {
            $this->application = $application ?? app();
            // if no connection, we setup the default along
            $this->setup($connection);
        }
    }

    /**
     * Sets up the database connection
     * @param mixed $connection
     */
    private function setup(Connection | Porm | array | string $connection = 'default'): void
    {
        if ($connection) {
            if ($connection instanceof Porm){
                $this->porm = $connection;
                return;
            } elseif ($connection instanceof Connection) {
                $this->connection = $connection;
                $this->application = $connection->getApplication();
                $this->porm = new Porm($this->connection);
                return;
            }
        }

        $this->connection = Connection::connect($this->application, $connection);
        $this->porm = new Porm($this->connection);
    }

    /**
     * Sets up the database connection
     * @param mixed $connection
     * @throws Exception
     */
    public function using(Connection | Porm | null|array|string $connection = 'default'): Porm
    {
        $this->setup($connection);
        return $this->porm;
    }


    /**
     * This sets the table to use
     *
     * @param string $table The table to use
     * @param string|null $alias The alias to use
     * @param string|null $using
     * @return Porm
     * @throws Exception
     * @example ```php
     *     Table::from('user') // notice this here
     *       ->get(['last_name' => 'Pionia']);
     * ```
     */
    public function from(string $table, ?string $alias = null, ?string $using = null): Porm
    {
        if($using) {
            $this->using($using);
        }
        $this->porm->setAlias($alias);
        $table = $this->porm->getAlias() ? $table . ' (' .$this->porm->getAlias() . ')' : $table;
        $this->porm->setTable($table);
        return $this->porm;
    }

    /**
     * This is for running queries in a containerised environment.
     * This should be the preferred in frameworks like Pionia
     *
     * @param string $table
     * @param string|null $alias
     * @param string|null $using
     * @return Porm
     * @throws Exception
     * @see from() if you are not using a container
     * @since v1.0.9 This method was updated to handle containerised environments only and should be used in frameworks like Pionia
     */
    public static function table(string $table, ?string $alias = null, ?string $using = null): Porm
    {
        return (new static())->from($table, $alias, $using);
    }

    public static function logs(): ?Arrayable
    {
        return app()->getSilently('query_pool');
    }

    /**
     * Returns the details of the current db connection
     * @return array
     */
    public function info(): array
    {
        return $this->porm->info();
    }
}
