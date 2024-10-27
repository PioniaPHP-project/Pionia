<?php

use DI\Container;
use JetBrains\PhpStorm\NoReturn;
use Pionia\Base\PioniaApplication;
use Pionia\Cache\PioniaCache;
use Pionia\Collections\Arrayable;
use Pionia\Collections\HighOrderTapProxy;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Services\Service;
use Pionia\Porm\Core\Porm;
use Pionia\Porm\Database\Db;
use Pionia\Templating\TemplateEngineInterface;
use Pionia\Utils\Support;
use Pionia\Validations\Validator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
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
        return app()->getEnv($key, $default);
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
        app()->setEnv($actual, $value);
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
    function response(int $returnCode = 0, ?string $returnMessage = null, mixed $returnData = null, mixed $extraData = null, ): BaseResponse
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

        // if we haven't created a new app instance we create it and return it;
        if (!defined('BASEPATH')) {
            define('BASEPATH', dirname(__DIR__, 2));
        }
        return (new PioniaApplication(BASEPATH))
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
     * Run any pionia-powered queries
     * @param string $tableName The name of the table to connect to
     * @param string|null $tableAlias
     * @param string|null $using
     * @return Porm|null
     * @throws Exception
     */
    function db(string $tableName, ?string $tableAlias = null, ?string $using = null): ?Porm
    {
       return table($tableName, $tableAlias, $using);
    }
}

if (!function_exists('table')){
    /**
     * Run any pionia-powered queries
     * @param string $tableName The name of the table to connect to
     * @param string|null $tableAlias The alias to use for the table provided
     * @param string|null $using The connection to use
     * @return Porm The porm instance for further chaining of queries
     * @throws Exception
     *
     * @example
     * ```php
     * table('users')->where(['username' => 'Pionia'])->get();
     * ```
     */
    function table(string $tableName, ?string $tableAlias = null, ?string $using = null): Porm
    {
        return Db::table($tableName, $tableAlias, $using);
    }
}


/**
 * Get any alias from the application container
 * @return array
 */
if (!function_exists('alias')) {
    function alias($key)
    {
        return app()->alias($key);
    }
}

if (!function_exists('directoryFor')) {
    /**
     * Get any directory from the application container
     */
    function directoryFor($key)
    {
        $dir = arr(allBuiltins()?->get('directories') ?? []);
        return $dir->get($key);
    }
}

if (!function_exists('yesNo')){
    /**
     * This function returns a yes or no phrase based on the condition
     * @param bool $condition The condition to check
     * @param string|null $yesPhrase The phrase to return if the condition is true
     * @param string|null $noPhrase The phrase to return if the condition is false
     * @return string
     */
    function yesNo(mixed $condition, ?string $yesPhrase = 'Yes', ?string $noPhrase = 'No'): string
    {
        return asBool($condition) ? $yesPhrase : $noPhrase;
    }
}

if (!function_exists(function: 'asBool')) {
    /**
     * convert a value to a boolean
     * @return array
     */
    function asBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}


if (!function_exists('write_ini_file')) {
    /**
     * Write an ini configuration file
     * This writes to the file in a lock-safe manner
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
    /**
     * Get the logger instance from the application container
     * @return LoggerInterface
     */
    function logger(): LoggerInterface
    {
        return app()?->logger;
    }
}



if (!function_exists('addIniSection')) {
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
    function addIniSection(string $section, ?array $keyValueToAppend = [], string $iniFile = 'generated.ini'): bool
    {
        $fs = new Filesystem();
        $file = app()->envPath($iniFile);
        if (!$fs->exists($file)) {
            $fs->touch($file);
        }
        $config = parse_ini_file($file, true);
        if ($config) {
            $config[$section] = array_merge($config[$section] ?? [], $keyValueToAppend);
        } else {
            $config = [$section => $keyValueToAppend];
        }
        if (writeIniFile($file, $config)) {
            logger()->info("Settings section $section altered successfully in $iniFile");
        }
        return true;
    }
}

if (!function_exists('cachedResponse')){
    /**
     * This function caches a response if the service has caching enabled.
     * Cached key is of the format `service_action` in camel case.
     * If no ttl is defined, caching will happen for only 60 seconds
     * @note This function is only available if the service has caching enabled
     * @note This is still under rigorous tests
     * @param Service $instance The service we are currently in, just pass `this` here!
     * @param BaseResponse $response The response object to cache, you can use `response()` for this!
     * @param mixed $ttl The time to live for the cache, defaults to 60 seconds
     * @return BaseResponse The cached response / the response you passed. It's not tampered with
     */
    function cachedResponse(Service $instance, BaseResponse $response, mixed $ttl= 60): BaseResponse
    {
        return tap($response, function (BaseResponse $response) use ($instance, $ttl) {
            if ($cacheinstance = app()->getSilently(PioniaCache::class)) {
                // caching is enabled, let's cache this response.
                $instance->setCacheInstance($cacheinstance);
                $instance->cacheTtl = $ttl;

                $service = $instance->request->getData()->get('service');
                $action = $instance->request->getData()->get('action');
                if ($service && $action) {
                    $key = Support::toSnakeCase($service . '_' . $action);
                    $instance->cache($key, $response->getPrettyResponse(), $ttl, true);
                }
            }
        });
    }
}

if (!function_exists('recached')){
    /**
     * Acronym for `cachedResponse` function but with more readable arguments
     * @param Service $instance The service we are currently in, just pass `this` here
     * @param int|null $returnCode The return code for the response, defaults to 0
     * @param string|null $returnMessage The return message for the response, defaults to null
     * @param mixed|null $returnData The return data for the response, defaults to null
     * @param mixed|null $extraData The extra data for the response, defaults to null
     * @param mixed $ttl The time to live for the cache, defaults to 60 seconds
     * @return BaseResponse The cached response / the response you passed. It's not tampered with
     * @note This function is only useful if the service has caching enabled
     */
    function recached(
        Service $instance,
        ?int $returnCode = 0,
        ?string $returnMessage=null,
        mixed $returnData = null,
        mixed $extraData = null,
        mixed $ttl = 60
    ): BaseResponse
    {
        return (new class ($instance, $returnCode, $returnMessage, $returnData, $extraData, $ttl) {
            private Service $instance;
            private int $returnCode;
            private string $returnMessage;
            private mixed $returnData;
            private mixed $extraData;
            private mixed $ttl;

            public function __construct(Service $instance, int $returnCode, string $returnMessage, mixed $returnData, mixed $extraData, mixed $ttl)
            {
                $this->instance = $instance;
                $this->returnCode = $returnCode;
                $this->returnMessage = $returnMessage;
                $this->returnData = $returnData;
                $this->extraData = $extraData;
                $this->ttl = $ttl;
            }

            public function handle(): BaseResponse
            {
                $response = response($this->returnCode, $this->returnMessage, $this->returnData, $this->extraData);
                return cachedResponse($this->instance, $response, $this->ttl);
            }
        })->handle();
    }
}


if (!function_exists('render')){
    /**
     * Render a template file from the templates folder.
     * @param $file
     * @param array|null $data
     * @return void
     */
    #[NoReturn]
    function render($file, ? array $data = []): void
    {
        app()->getSilently(TemplateEngineInterface::class)?->view($file, $data);
        exit(1);
    }
}

if (!function_exists('parseHtml')){
    /**
     * Get all the builtins
     * @param $file
     * @param array|null $data
     * @return array
     */
    function parseHtml($file, ? array $data = []): array
    {
        return app()->getSilently(TemplateEngineInterface::class)?->parse($file, $data);
    }
}

if (!function_exists('asset')){
    /**
     * Get the asset path
     * @param $file
     * @param string|null $dir
     * @return string|null
     */
    function asset($file, ?string $dir = null): ?string
    {
        if (!$dir){
            $dir = alias(DIRECTORIES::STATIC_DIR->name);
        }
        $pkg = new PathPackage($dir, new EmptyVersionStrategy());
        $path = $pkg->getUrl($file);
        if (file_exists($path)) {
            return $path;
        }
        return null;
    }
}


if (! function_exists('blank')) {
    /**
     * Determine if the given value is "blank".
     *
     * @phpstan-assert-if-false !=null|'' $value
     *
     * @phpstan-assert-if-true !=numeric|bool $value
     *
     * @param  mixed  $value
     * @return bool
     */
    function blank(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        if ($value instanceof Stringable) {
            return trim((string) $value) === '';
        }

        if (is_a($value, Arrayable::class)) {
            return $value->isEmpty();
        }

        return empty($value);
    }
}

if (!function_exists('validate')){
    /**
     * Validate data
     * @param string $field
     * @param Arrayable|Request|Service $data
     * @return Validator
     */
    function validate(string $field, Arrayable | Request | Service $data): Validator
    {
        if ($data instanceof Service) {
            $data = $data->request->getData();
        } elseif ($data instanceof Request) {
            $data = $data->getData();
        }
        return Validator::validate($field, $data);
    }
}

if (!function_exists('toCamelCase')){
    /**
     * Convert a string to camel case
     */
    function toCamelCase(string $value): string
    {
        return Support::toCamelCase($value);
    }
}

if (!function_exists('toSnakeCase')){
    /**
     * Convert a string to snake case
     */
    function toSnakeCase(string $value): string
    {
        return Support::toSnakeCase($value);
    }
}

if (!function_exists('classify')){
    /**
     * Convert a string to a class name like format
     */
    function classify(string $value): string
    {
        return Support::classify($value);
    }
}

if (!function_exists('arrayToString')){
    /**
     * Convert an array to a string
     */
    function arrayToString(array $value, ?string $separator = ','): string
    {
        return Support::arrayToString($value, $separator);
    }
}

if (!function_exists('slugify')){
    /**
     * Convert a string to a slug-like format
     */
    function slugify(string $value): string
    {
        return Support::slugify($value);
    }
}

if (!function_exists('singularize')){
    /**
     * Convert a string to a singular form
     */
    function singularize(string $word): string
    {
        return Support::singularize($word);
    }
}

if (!function_exists('pluralize')){
    /**
     * Convert a string to a plural form
     */
    function pluralize(string $word): string
    {
        return Support::pluralize($word);
    }
}

if (!function_exists('capitalize')){
    /**
     * Capitalize a string
     */
    function capitalize(string $phrase): string
    {
        return Support::capitalize($phrase);
    }
}

if (!function_exists('jsonify')){
    /**
     * Convert anything to a json string
     */
    function jsonify(mixed $phrase): string
    {
        return Support::jsonify($phrase);
    }
}

if (!function_exists('flatten')){
    /**
     * Flatten an array
     * @example flatten(['a', 'b', ['c', 'd']]) => ['a', 'b', 'c', 'd']
     * @param array $flatten
     * @return array
     */
    function flatten(array $flatten): array
    {
        return Support::arrFlatten($flatten);
    }
}


if (!function_exists('is_cached_in')){
    function is_cached_in($keyCached, $keyToCheck): bool
    {
        return app()->isCachedIn($keyCached, $keyToCheck);
    }
}
