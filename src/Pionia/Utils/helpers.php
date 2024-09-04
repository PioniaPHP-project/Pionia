<?php

use DI\Container;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Http\Response\BaseResponse;
use Pionia\Pionia\Utils\Arrayable;
use Pionia\Pionia\Utils\HighOrderTapProxy;
use Pionia\Pionia\Utils\Support;
use Porm\Core\Database;
use Porm\Porm;

if (! function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @template TValue
     *
     * @param  TValue  $value
     * @param (callable(TValue): mixed)|null $callback
     * @return HighOrderTapProxy|TValue
     */
    function tap($value, callable $callback = null)
    {
        if (is_null($callback)) {
            return new HighOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }
}

if (! function_exists('arr')) {
    /**
     * Get an item from an array using "dot" notation.
     *
     * @param ?array $array
     * @return Arrayable
     */
    function arr(?array $array): Arrayable
    {
        return new Arrayable($array);
    }
}

if (! function_exists('env')) {
    /**
     * Get an item from the environment. Or default if it does not exist.
     *
     * If both the key and the default value are null, the function should return the entire environment.
     */
    function env(?string $key =null, mixed $default = null): mixed
    {
        $env = arr($_SERVER);
        $env->merge($_ENV);
        if ($key === null) {
            return $env;
        }
        return $env->get($key, $default);
    }
}

if (! function_exists('setEnv')) {
    /**
     * Adds a temporary key-value to the environment.
     *
     * This can only be retrieved using the `env` function.
     */
    function setEnv(string $key, mixed $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

if (!function_exists('response')) {
    /**
     * Helper function to return a response
     * @param $returnCode int
     * @param string|null $returnMessage
     * @param mixed $returnData
     * @param mixed $extraData
     * @return BaseResponse
     */
    function response(int $returnCode = 0, ?string $returnMessage = null, mixed $returnData = null, mixed $extraData = null): BaseResponse
    {
        return BaseResponse::jsonResponse($returnCode, $returnMessage, $returnData, $extraData);
    }
}


if (!function_exists('app')) {
    /**
     * Helper function to return the application instance
     */
    function app(): PioniaApplication
    {
        if (isset($GLOBALS['app'])) {
            return $GLOBALS['app'];
        }

        return (new PioniaApplication())
            ->powerUp();
    }
}

if (!function_exists('container')) {
    /**
     * Helper function to return the application container
     */
    function container(): Container
    {
        return app()->context;
    }
}

if (!function_exists('db')) {
    /**
     * Helper function to return the request object
     * @param string $tableName The name of the table to connect to
     * @param string|null $connToUse If defined, it will use the connection name to connect to the database
     * @throws Exception
     */
    function db(string $tableName, ?string $alias = null, ?string $connToUse = null, ?bool $silent= false): ?Porm
    {
        $databases = arr(env('databases'));

        if ($databases->isEmpty()) {
            return null;
        }

        // here, no connection was defined
        if ($databases->get('size') < 1) {
            return null;
        }
        $defaultConn = $databases->get('default');

        if (!$connToUse) {
            $db = app()->getSilently($defaultConn);
        } else {
            $conn = $databases->get($connToUse);
            // if the developer passed the default, we will use the default connection
            if ($connToUse === $defaultConn){
                $db = app()->getSilently($defaultConn);
            } else {
                $dbFile = env('PIONIA_DATABASE_CONFIG_PATH');
                if ($databases->has($connToUse)) {
                    $db = app()->contextMakeSilently(Porm::class, ['connection' => $conn, 'dbFile' => $dbFile]);
                } else {
                    if ($silent) {
                        return null;
                    } else {
                        throw new Exception("The connection $connToUse does not exist in the database configuration, available connections are: " . Support::arrayToString($databases->get('connections')));
                    }
                }
            }
        }

        if (!$db) {
            return null;
        }

        app()->context->set('pioniaSqlDb', $db);
        return app()->getSilently('pioniaSqlDb')->table($tableName, $alias);
    }
}

