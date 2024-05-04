<?php

namespace jetPhp\database;

/**
 * Extend this to define your own database driver implementation.
 *
 * By default, postgres, mysql and sqlite are supported.
 *
 * @author Tumusiime Ezra -- jet2018
 *
 * @property $datasource Can be provided but must be as per PDO datasource format.
 */
interface ConnectionInterface
{
    public static function __connect_internal(string $driver, string $host,
                                   string $dbname, string $dbuser,
                                   string $dbpassword, int | null $port = null,
                                   array $params = [],
                                   string | null $datasource = null
    );
}
