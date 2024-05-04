<?php

namespace jetPhp\database;

use jetPhp\core\Base;
use jetPhp\exceptions\DatabaseException;
use PDO;

class Connector extends Base implements ConnectionInterface
{

    private static string | null $dbType = null;

    /**
     * @throws DatabaseException
     */
     static function __connect_internal(string | null $driver, string | null $host, string | null $dbname,
                                    string | null $dbuser, string | null $dbpassword,
                                    int | null    $port = null, array | null $params = [],
                                    string | null $datasource = null
    ): PDO
     {
        // establish the db type and set it from here
        if ($driver == 'mysql'){
            self::$dbType = SupportDatabaseDrivers::$MYSQL;
        } elseif ($driver == 'pgsql'){
            self::$dbType = SupportDatabaseDrivers::$POSTGRES;
        } elseif ($driver == 'sqlite'){
            self::$dbType = SupportDatabaseDrivers::$SQLITE;
        } else {
            throw new DatabaseException("Unsupported driver, must be one of [sqlite, mysql, pgsql]");
        }

        $params = $params ?: [];

        if (!array_key_exists(PDO::ATTR_PERSISTENT, $params)){
            $params[PDO::ATTR_PERSISTENT] = true;
        }

        if (self::$dbType =='MYSQL'){
            $params[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8';
        }

        if (!array_key_exists(PDO::ATTR_DEFAULT_FETCH_MODE, $params)){
            $params[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        }

        self::$settings['current_db_driver'] = self::$dbType;

        try {
            if (self::$dbType == 'POSTGRES'){
                $dsn = "$driver:host=$host;port=$port;dbname=$dbname;user=$dbuser;password=$dbpassword";
                return new PDO($dsn, null, null, $params);
            } elseif (self::$dbType == 'MYSQL') {
                $dsn = "$driver:host=$host;dbname=$dbname;charset=UTF8";
                return new PDO($dsn, $dbuser, $dbpassword, $params);
            } else {
                return new PDO($datasource, null, null, $params);
            }
        } catch (\PDOException $exception){
            throw new DatabaseException($exception->getMessage());
        }
    }

    /**
     * @throws DatabaseException
     */
    public static function withSqlite($datasource) : PDO
    {
        echo "Running on SQLITE in live environment is highly unrecommended, switch to better databases like postgres or mysql 
         to avoid uncertain behaviour.";
        return self::__connect_internal('sqlite', null, null, null, null, null, [], $datasource);
    }

    /**
     * @throws DatabaseException
     */
    public static function connect(string | null $using = 'db'): PDO
    {
        $settings_file = self::resolveSettingsFromIni();

        if (!$using) {
            $using = 'db';
        }

        if (!array_key_exists($using, $settings_file)){
            throw new DatabaseException("SETTINGS ERROR: Could not find the defined data source, are you sure you defines a db connection names ".$using);
        }

        $params = $settings_file[$using];
        if (!array_key_exists('driver', $params)){
            throw new DatabaseException("You must define the driver in your ".$using." connection in ".SETTINGS);
        }
        $driver = $params['driver'];
        if ($driver === 'sqlite'){
            if (!array_key_exists('name', $params)){
                $name = 'database';
            } else {
                $name = $params['name'];
            }
            return self::withSqlite($name);
        } else {
            $host = $params['host'] ?? 'localhost';
            $port = $params['port'];
            $name= $params['name'] ?? throw new DatabaseException("Schemas name is not defined in ".$using);
            $user = $params['user'];
            $password = $params['password'] || '';
            return self::__connect_internal($driver, $host, $name, $user, $password, $port);
        }
    }


}
