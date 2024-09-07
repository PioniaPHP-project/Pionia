<?php

use DI\Container;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Http\Response\BaseResponse;
use Pionia\Pionia\Utils\Arrayable;
use Pionia\Pionia\Utils\HighOrderTapProxy;
use Pionia\Pionia\Utils\Support;
use Porm\Porm;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

if (! function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * Value returned is not transformed by the closure.
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
        $env = [...$_ENV, $_SERVER];
        $actual = $key;
        if (array_key_exists(strtoupper($key), $env)){
            $actual = strtoupper($key);
        } elseif (array_key_exists(strtolower($key), $env)){
            $actual = strtolower($key);
        }
        $_ENV[$actual] = $value;
        $_SERVER[$actual] = $value;
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


if (!function_exists('alias')) {
    function alias($key)
    {
        return app()->alias($key);
    }
}

if (!function_exists('directoryFor')) {
    function directoryFor($key)
    {
        $dir = arr(allBuiltins()?->get('directories') ?? []);
        return $dir->get($key);
    }
}

if (!function_exists('yesNo')){
    function yesNo(bool $condition, ?string $yesPhrase = 'Yes', ?string $noPhrase = 'No'): string
    {
        return $condition ? $yesPhrase : $noPhrase;
    }
}


if (!function_exists('write_ini_file')) {
    /**
     * Write an ini configuration file
     *
     * @param string $file
     * @param array  $array
     * @return bool
     */
    function writeIniFile(string $file, array $array = []): bool
    {
        // process array
        $data = array();
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $data[] = "[$key]";
                foreach ($val as $skey => $sval) {
                    if (is_array($sval)) {
                        foreach ($sval as $_skey => $_sval) {
                            if (is_numeric($_skey)) {
                                $data[] = $skey.'[] = '.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
                            } else {
                                $data[] = $skey.'['.$_skey.'] = '.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
                            }
                        }
                    } else {
                        $data[] = $skey.' = '.(is_numeric($sval) ? $sval : (ctype_upper($sval) ? $sval : '"'.$sval.'"'));
                    }
                }
            } else {
                $data[] = $key.' = '.(is_numeric($val) ? $val : (ctype_upper($val) ? $val : '"'.$val.'"'));
            }
            // empty line
            $data[] = null;
        }

        // open file pointer, init flock options
        $fp = fopen($file, 'w');
        $retries = 0;
        $max_retries = 100;

        if (!$fp) {
            return false;
        }

        // loop until get lock, or reach max retries
        do {
            if ($retries > 0) {
                usleep(rand(1, 5000));
            }
            $retries += 1;
        } while (!flock($fp, LOCK_EX) && $retries <= $max_retries);

        // couldn't get the lock
        if ($retries == $max_retries) {
            return false;
        }

        // got lock, write data
        fwrite($fp, implode(PHP_EOL, $data).PHP_EOL);

        // release lock
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }
}


if (!function_exists('logger')){
    function logger(): LoggerInterface
    {
        return app()?->logger;
    }
}


/**
 * This function adds a new section to an ini file
 * We generally use this to generate and add new sections to the generated.ini file
 * which holds settings for the auto-generated files
 *
 * This function will create the file if it does not exist,
 * add the section if it does not exist or update the section if it exists
 * @param string $section
 * @param array|null $keyValueToAppend
 * @param string $iniFile
 * @return bool
 */
 function addIniSection(string $section, ?array $keyValueToAppend = [], string $iniFile='generated.ini'): bool
{
    $fs = new Filesystem();
    $file = app()->envPath($iniFile);
    if (!$fs->exists($file)){
        $fs->touch($file);
    }
    $config = parse_ini_file($file, true);
    if ($config){
        $config[$section] = array_merge($config[$section] ?? [], $keyValueToAppend);
    } else {
        $config = [$section => $keyValueToAppend];
    }
    if (writeIniFile($file, $config)){
        logger()->info("Settings section $section altered successfully in $iniFile");
    }
    return true;
}
