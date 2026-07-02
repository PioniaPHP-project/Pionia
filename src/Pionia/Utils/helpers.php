<?php

use JetBrains\PhpStorm\NoReturn;
use Pionia\Cache\PioniaCache;
use Pionia\Collections\Arrayable;
use Pionia\Collections\HighOrderTapProxy;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\ApiResponse;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Http\Routing\Router\RouteObject;
use Pionia\Http\Services\Service;
use Pionia\Porm\Core\Porm;
use Pionia\Porm\Database\Db;
use Pionia\Realm\AppRealm;
use Pionia\Realm\RealmContract;
use Pionia\Runtime\RuntimeMode;
use Pionia\Templating\TemplateEngineInterface;
use Pionia\Utils\Support;
use Pionia\Validations\ValidationManager;
use Pionia\Validations\ValidationRules;
use Pionia\Validations\Validator;
use Psr\Log\LoggerInterface;
use Pionia\Utils\Filesystem;
use Pionia\Http\Routing\RouteTable;

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
    function tap($value, ?callable $callback = null)
    {
        if (is_null($callback)) {
            return new HighOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }
}

if (! function_exists('route')){
    /**
     * @deprecated Use {@see router()} instead.
     */
    #[\Deprecated(message: 'Use router() instead', since: '3.0')]
    function route(RealmContract $app): PioniaRouter
    {
        return router($app);
    }
}

if (! function_exists('router')){
    function router(RealmContract $app): PioniaRouter
    {
        return new PioniaRouter($app);
    }
}

if (! function_exists('arr')) {
    /**
     * Get an item from an array using "dot" notation.
     *
     * @param Arrayable|array|null $array $array
     * @return Arrayable
     */
    function arr(null | Arrayable | array $array = []): Arrayable
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
        return app()->env($key, $default);
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
        realm()->setEnv($actual, $value);
    }
}

if (!function_exists('pionia_handle_exception')) {
    /**
     * Resolve a throwable through the configured global exception handler.
     */
    function pionia_handle_exception(\Throwable $e, ?Request $request = null): ApiResponse
    {
        return realm()->exceptions()->handle($e, $request);
    }
}

if (!function_exists('report')) {
    /**
     * Report an exception or message through the exception pipeline logger.
     */
    function report(\Throwable|string $message, array $context = []): void
    {
        if ($message instanceof \Throwable) {
            realm()->exceptions()->report($message);

            return;
        }

        logger()->error($message, $context);
    }
}



if (!function_exists('response')) {
    /**
     * Helper function to return a response
     */
    #[\NoDiscard]
    function response(int $returnCode = 0, ?string $returnMessage = null, mixed $returnData = null, mixed $extraData = null, ): ApiResponse
    {
        return ApiResponse::jsonResponse($returnCode, $returnMessage, $returnData, $extraData);
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
    #[\NoDiscard]
    function table(string $tableName, ?string $tableAlias = null, ?string $using = null): Porm
    {
        return Db::table($tableName, $tableAlias, $using);
    }
}

if (!function_exists('connectionManager')) {
    #[\NoDiscard]
    function connectionManager(): \Pionia\Porm\ConnectionManager
    {
        return app()->get(\Pionia\Porm\ConnectionManager::class);
    }
}



/**
 * Resolve a path alias registered on the realm (e.g. `PUBLIC_DIR`, `STORAGE_DIR`).
 *
 * @return mixed
 */
if (!function_exists('alias')) {
    function alias($key)
    {
        return app()->alias($key);
    }
}

if (!function_exists('namespaceFor')) {
    /**
     * Resolve a PHP namespace key from app.namespaces (e.g. SERVICE_NS → Application\Services).
     */
    function namespaceFor(string $key): string
    {
        $namespaces = app()->get(\Pionia\Realm\AppRealm::NAMESPACES_TAG);
        $ns = is_object($namespaces) ? $namespaces->get($key) : null;

        if (!is_string($ns) || $ns === '') {
            throw new \InvalidArgumentException("Unknown application namespace [{$key}].");
        }

        return $ns;
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

if (!function_exists('directoryPath')) {
    /**
     * Resolve an absolute application directory path from a DIRECTORIES key (e.g. SERVICES_DIR).
     */
    function directoryPath(string $key): string
    {
        $path = alias($key);

        if (!is_string($path) || $path === '') {
            throw new \InvalidArgumentException("Unknown application directory [{$key}].");
        }

        return $path;
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
     * Coerce a value to boolean (accepts "true", "1", "yes", etc.).
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

if (!function_exists('path')){
    function path(string $path): string
    {
        return app()->appRoot($path);
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
     * @param Service $instance The service we are currently in, just pass `this` here!
     * @param ApiResponse $response The response object to cache, you can use `response()` for this!
     * @param mixed $ttl The time to live for the cache, defaults to 60 seconds
     * @return ApiResponse The cached response / the response you passed. It's not tampered with
     */
    function cachedResponse(Service $instance, ApiResponse $response, mixed $ttl= 60): ApiResponse
    {
        return tap($response, function (ApiResponse $response) use ($instance, $ttl) {
            if ($cacheinstance = app()->getSilently(PioniaCache::class)) {
                // caching is enabled, let's cache this response.
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
     * @return ApiResponse The cached response / the response you passed. It's not tampered with
     * @note This function is only useful if the service has caching enabled
     */
    function recached(
        Service $instance,
        ?int $returnCode = 0,
        ?string $returnMessage=null,
        mixed $returnData = null,
        mixed $extraData = null,
        mixed $ttl = 60
    ): ApiResponse
    {
        return cachedResponse(
            $instance,
            response($returnCode, $returnMessage, $returnData, $extraData),
            $ttl
        );
    }
}


if (!function_exists('renderToString')){
    /**
     * Render a template without terminating the process.
     */
    function renderToString($file, ?array $data = []): string
    {
        ob_start();
        app()->getSilently(TemplateEngineInterface::class)?->view($file, $data);

        return (string) ob_get_clean();
    }
}

if (!function_exists('render')){
    /**
     * Render a template file. In FPM/CLI mode the script exits after output; in worker/testing mode it returns.
     *
     * @deprecated Use renderToString() and send the result yourself.
     */
    #[\Deprecated(message: 'Use renderToString() instead', since: '3.0')]
    function render($file, ?array $data = []): void
    {
        echo renderToString($file, $data);

        if (runtimeMode() === RuntimeMode::Worker || runtimeMode() === RuntimeMode::Testing) {
            return;
        }

        exit(0);
    }
}

if (!function_exists('post')) {
    function post(string $path): RouteObject
    {
        return RouteObject::post($path);
    }
}


if (!function_exists('get')) {
    function get(string $path): RouteObject
    {
        return RouteObject::get($path);
    }
}

if (!function_exists('allRoutes')){
    function allRoutes(): RouteTable
    {

        return app()->getRoutes();
    }
}

if (!function_exists('baseUrl')){
    /**
     * Unversioned API prefix from the `API_BASE` environment variable.
     *
     * Prefer {@see apiBase()} in application code — it reads the value registered on the realm.
     *
     * @return string e.g. `/api/`
     */
    function baseUrl(): string
    {
        $base =  env('API_BASE', '/api/');
        if (!str_starts_with($base, '/')) {
            $base = '/'.$base;
        }
        if (!str_ends_with($base, '/')) {
            $base .= '/';
        }
        return $base;
    }
}

if (!function_exists('parseHtml')){
    /**
     * Parse a template file into structured data (no output).
     *
     * @return array<string, mixed>|null
     */
    function parseHtml($file, ? array $data = []): ?array
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
        if (!$dir) {
            $dir = alias(DIRECTORIES::STATIC_DIR->name);
        }

        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim((string) $file, DIRECTORY_SEPARATOR);

        return is_file($path) ? $path : null;
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
     * Validate a single field (chainable).
     *
     * @example validate('email', $data)->required()->email();
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

if (!function_exists('rules')) {
    /**
     * Validate multiple fields with pipe rules.
     *
     * @param array<string, string|array<int, string>> $fieldRules
     *
     * @example rules($data, ['email' => 'required|email', 'password' => 'required|password|min:8']);
     */
    function rules(Arrayable | Request | Service $data, array $fieldRules): void
    {
        if ($data instanceof Service) {
            $data = $data->request->getData();
        } elseif ($data instanceof Request) {
            $data = $data->getData();
        }

        ValidationRules::check($data, $fieldRules);
    }
}

if (!function_exists('validations')) {
    /**
     * Shared validation rule registry (singleton per application).
     */
    function validations(): ValidationManager
    {
        return app()->get(ValidationManager::class);
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


if (!function_exists('realm')) {
    #[\NoDiscard]
    function realm(): AppRealm
    {
        return container();
    }
}

if (!function_exists('event')) {
    function event(object $event,?string $eventName = null): object
    {
        return realm()->event()->dispatch($event, $eventName);
    }
}

if (!function_exists('listen')) {
    function listen(string $eventName, array|callable $listener, int $priority = 0): void
    {
        realm()->event()->addListener($eventName, $listener, $priority);
    }
}

if (!function_exists('container')) {
    /**
     * @see app(), realm(), pionia()
     */
    #[\NoDiscard]
    function container(): AppRealm
    {
        static $instance = null;

        if ($instance === null) {
            $instance = require container_path();
        }

        return $instance;
    }
}


if (!function_exists('app')) {
    /**
     * Instance of the application container
     */
    #[\NoDiscard]
    function app(): AppRealm
    {
        return container();
    }
}

if (!function_exists('runtimeMode')) {
    function runtimeMode(): RuntimeMode
    {
        static $mode = null;
        if ($mode instanceof RuntimeMode) {
            return $mode;
        }

        if (defined('PIONIA_RUNTIME')) {
            $mode = RuntimeMode::tryFrom((string) PIONIA_RUNTIME) ?? RuntimeMode::Fpm;

            return $mode;
        }

        if (defined('PIONIA_TESTING') && PIONIA_TESTING) {
            $mode = RuntimeMode::Testing;

            return $mode;
        }

        $mode = PHP_SAPI === 'cli' ? RuntimeMode::Cli : RuntimeMode::Fpm;

        return $mode;
    }
}

if (!function_exists('setRuntimeMode')) {
    function setRuntimeMode(RuntimeMode $mode): void
    {
        if (!defined('PIONIA_RUNTIME')) {
            define('PIONIA_RUNTIME', $mode->value);
        }
    }
}




if (!function_exists('commands')) {
    /**
     * Returns all commands that have been registered in the container
     */
    function commands(): Arrayable
    {
        return container()->getSilently(AppRealm::COMMANDS_TAG);
    }
}

if (!function_exists('services')) {
    /**
     * Returns all services that have been registered in the container
     */
    function services(?string $key = null): array
    {
        $services = container()->getSilently(AppRealm::SERVICES_TAG);
        if ($services instanceof Arrayable) {
            $services = $services->all();
        } elseif (!is_array($services)) {
            $services = (array) $services;
        }

        if ($key) {
            return $services[$key] ?? [];
        }

        return $services;
    }
}

if (!function_exists('aliases')) {
    /**
     * Returns all aliases that have been registered in the container
     */
    function aliases(): Arrayable
    {
        return container()->getSilently(AppRealm::ALIASES_TAG);
    }
}

if (!function_exists('auths')) {
    /**
     * Returns all authentications that have been registered in the container
     */
    function authentications(): Arrayable
    {
        return container()->getSilently(AppRealm::AUTHENTICATIONS_TAG);
    }
}

if (!function_exists('middlewares')) {
    /**
     * Returns all middlewares that have been registered in the container
     */
    function middlewares()
    {
        return container()->getSilently(AppRealm::MIDDLEWARE_TAG);
    }
}

if (!function_exists("apiBase")) {
    /**
     * Unversioned API prefix registered on the realm (default `/api/`).
     *
     * @see apiVersionPath() for `/api/v1/`
     * @see apiPingPath() for `/api/v1/ping`
     */
    function apiBase() {
        return app()->getSilently(app()::APP_API_BASE_TAG);
    }
}

if (!function_exists('defaultApiVersion')) {
    /**
     * First registered switch version, e.g. v1.
     */
    function defaultApiVersion(): string
    {
        $switches = app()->getSilently(AppRealm::SWITCHES_TAGS);
        if ($switches instanceof Arrayable) {
            $keys = array_keys($switches->all());
        } else {
            $keys = array_keys((array) $switches);
        }

        return $keys[0] ?? 'v1';
    }
}

if (!function_exists('apiVersionPath')) {
    /**
     * Versioned API prefix, e.g. /api/v1/
     */
    #[\NoDiscard]
    function apiVersionPath(?string $version = null): string
    {
        $version ??= defaultApiVersion();
        $base = rtrim((string) apiBase(), '/');

        return $base . '/' . trim($version, '/') . '/';
    }
}

if (!function_exists('apiPingPath')) {
    /**
     * Status endpoint for a switch version, e.g. /api/v1/ping
     */
    #[\NoDiscard]
    function apiPingPath(?string $version = null): string
    {
        return rtrim(apiVersionPath($version), '/') . '/ping';
    }
}

if (!function_exists('apiCatalogPath')) {
    /**
     * Debug-only Moonlight catalog path for a versioned API (requires APP_DEBUG).
     */
    #[\NoDiscard]
    function apiCatalogPath(?string $version = null): string
    {
        return rtrim(apiVersionPath($version), '/') . '/__catalog';
    }
}


if (!function_exists('apiDocsConfig')) {
    /**
     * @return array<string, mixed>
     */
    function apiDocsConfig(): array
    {
        $docs = env('docs', []);

        return is_array($docs) ? $docs : [];
    }
}

if (!function_exists('apiDocsEnabled')) {
    /**
     * Whether runtime API docs are exposed.
     *
     * Explicit `[docs] ENABLED` / `DOCS_ENABLED` overrides; otherwise follows `DEBUG`.
     */
    function apiDocsEnabled(): bool
    {
        $docs = apiDocsConfig();

        foreach (['ENABLED', 'enabled'] as $key) {
            if (array_key_exists($key, $docs)) {
                return filter_var($docs[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $explicit = env('DOCS_ENABLED');
        if ($explicit !== null && $explicit !== '') {
            return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        }

        return isDebug();
    }
}

if (!function_exists('apiDocsToken')) {
    /**
     * Optional shared secret for docs routes. Null means no token required.
     */
    function apiDocsToken(): ?string
    {
        $docs = apiDocsConfig();

        foreach (['TOKEN', 'token'] as $key) {
            if (!empty($docs[$key]) && is_string($docs[$key])) {
                return $docs[$key];
            }
        }

        $token = env('DOCS_TOKEN');

        return is_string($token) && $token !== '' ? $token : null;
    }
}

if (!function_exists('apiDocsAuthorized')) {
    /**
     * Whether the request satisfies the optional docs token gate.
     */
    function apiDocsAuthorized(Request $request): bool
    {
        $required = apiDocsToken();
        if ($required === null) {
            return true;
        }

        $provided = (string) ($request->headers->get('X-Docs-Token') ?? $request->query->get('token') ?? '');

        return $provided !== '' && hash_equals($required, $provided);
    }
}

if (!function_exists('apiStatsConfig')) {
    /**
     * @return array<string, mixed>
     */
    function apiStatsConfig(): array
    {
        $stats = env('stats', []);

        return is_array($stats) ? $stats : [];
    }
}

if (!function_exists('apiStatsEnabled')) {
    /**
     * Whether the developer stats dashboard is exposed.
     *
     * Explicit `[stats] ENABLED` / `STATS_ENABLED` overrides; otherwise follows `DEBUG`.
     */
    function apiStatsEnabled(): bool
    {
        $stats = apiStatsConfig();

        foreach (['ENABLED', 'enabled'] as $key) {
            if (array_key_exists($key, $stats)) {
                return filter_var($stats[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $explicit = env('STATS_ENABLED');
        if ($explicit !== null && $explicit !== '') {
            return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        }

        return isDebug();
    }
}

if (!function_exists('apiStatsToken')) {
    function apiStatsToken(): ?string
    {
        $stats = apiStatsConfig();

        foreach (['TOKEN', 'token'] as $key) {
            if (!empty($stats[$key]) && is_string($stats[$key])) {
                return $stats[$key];
            }
        }

        $token = env('STATS_TOKEN');

        return is_string($token) && $token !== '' ? $token : null;
    }
}

if (!function_exists('apiStatsAuthorized')) {
    function apiStatsAuthorized(Request $request): bool
    {
        $required = apiStatsToken();
        if ($required === null) {
            return true;
        }

        $provided = (string) ($request->headers->get('X-Stats-Token') ?? $request->query->get('token') ?? '');

        return $provided !== '' && hash_equals($required, $provided);
    }
}


if (!function_exists('maintenanceConfig')) {
    /**
     * @return array<string, mixed>
     */
    function maintenanceConfig(): array
    {
        $path = null;
        try {
            $path = app()->envPath('settings.ini');
        } catch (\Throwable) {
            $path = null;
        }

        if (is_string($path) && is_file($path)) {
            clearstatcache(true, $path);
            $settings = parse_ini_file($path, true);
            $section = $settings['maintenance'] ?? null;

            return is_array($section) ? $section : [];
        }

        $maintenance = env('maintenance', []);

        return is_array($maintenance) ? $maintenance : [];
    }
}

if (!function_exists('maintenanceModeEnabled')) {
    function maintenanceModeEnabled(): bool
    {
        $explicit = env('MAINTENANCE_MODE');
        if ($explicit !== null && $explicit !== '') {
            return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        }

        $maintenance = maintenanceConfig();

        foreach (['ENABLED', 'enabled'] as $key) {
            if (array_key_exists($key, $maintenance)) {
                return filter_var($maintenance[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return false;
    }
}

if (!function_exists('maintenanceMessage')) {
    function maintenanceMessage(): string
    {
        $maintenance = maintenanceConfig();

        foreach (['MESSAGE', 'message'] as $key) {
            if (!empty($maintenance[$key]) && is_string($maintenance[$key])) {
                return $maintenance[$key];
            }
        }

        $message = env('MAINTENANCE_MESSAGE');

        return is_string($message) && $message !== ''
            ? $message
            : 'The application is undergoing scheduled maintenance. Please try again shortly.';
    }
}

if (!function_exists('maintenanceRetryAfter')) {
    function maintenanceRetryAfter(): ?int
    {
        $maintenance = maintenanceConfig();

        foreach (['RETRY_AFTER', 'retry_after'] as $key) {
            if (isset($maintenance[$key]) && is_numeric($maintenance[$key])) {
                return max(0, (int) $maintenance[$key]);
            }
        }

        $retryAfter = env('MAINTENANCE_RETRY_AFTER');

        return is_numeric($retryAfter) ? max(0, (int) $retryAfter) : null;
    }
}

if (!function_exists('maintenanceBypassToken')) {
    function maintenanceBypassToken(): ?string
    {
        $maintenance = maintenanceConfig();

        foreach (['BYPASS_TOKEN', 'bypass_token', 'TOKEN', 'token'] as $key) {
            if (!empty($maintenance[$key]) && is_string($maintenance[$key])) {
                return $maintenance[$key];
            }
        }

        $token = env('MAINTENANCE_BYPASS_TOKEN');

        return is_string($token) && $token !== '' ? $token : null;
    }
}

if (!function_exists('maintenanceBypassAuthorized')) {
    function maintenanceBypassAuthorized(Request $request): bool
    {
        $required = maintenanceBypassToken();
        if ($required === null) {
            return false;
        }

        $provided = (string) ($request->headers->get('X-Maintenance-Bypass')
            ?? $request->query->get('bypass')
            ?? '');

        return $provided !== '' && hash_equals($required, $provided);
    }
}

if (!function_exists('frontendConfig')) {
    /**
     * @return array<string, mixed>
     */
    function frontendConfig(): array
    {
        $path = null;
        if (defined('BASE_PATH') && is_file(BASE_PATH . '/environment/settings.ini')) {
            $path = BASE_PATH . '/environment/settings.ini';
        } else {
            try {
                $path = app()->envPath('settings.ini');
            } catch (\Throwable) {
                $path = null;
            }
        }

        if (is_string($path) && is_file($path)) {
            clearstatcache(true, $path);
            $settings = parse_ini_file($path, true);
            $section = $settings['frontend'] ?? null;

            return is_array($section) ? $section : [];
        }

        $frontend = env('frontend', []);

        return is_array($frontend) ? $frontend : [];
    }
}

if (!function_exists('spaFallbackEnabled')) {
    function spaFallbackEnabled(): bool
    {
        $frontend = frontendConfig();

        foreach (['SPA_FALLBACK', 'spa_fallback'] as $key) {
            if (array_key_exists($key, $frontend)) {
                return filter_var($frontend[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (defined('BASE_PATH')) {
            $userIndex = BASE_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.html';

            return is_file($userIndex);
        }

        return false;
    }
}

if (!function_exists('jobsConfig')) {
    /**
     * @return array<string, mixed>
     */
    function jobsConfig(): array
    {
        $path = null;
        if (defined('BASE_PATH') && is_file(BASE_PATH . '/environment/settings.ini')) {
            $path = BASE_PATH . '/environment/settings.ini';
        } else {
            try {
                $path = app()->envPath('settings.ini');
            } catch (\Throwable) {
                $path = null;
            }
        }

        if (is_string($path) && is_file($path)) {
            clearstatcache(true, $path);
            $settings = parse_ini_file($path, true);
            $section = $settings['jobs'] ?? null;

            return is_array($section) ? $section : [];
        }

        $jobs = env('jobs', []);

        return is_array($jobs) ? $jobs : [];
    }
}

if (!function_exists('moonlightJobsEnabled')) {
    function moonlightJobsEnabled(): bool
    {
        $jobs = jobsConfig();

        foreach (['ENABLED', 'enabled'] as $key) {
            if (array_key_exists($key, $jobs)) {
                return filter_var($jobs[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return filter_var(env('JOBS_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('realtimeConfig')) {
    /**
     * @return array<string, mixed>
     */
    function realtimeConfig(): array
    {
        $path = null;
        if (defined('BASE_PATH') && is_file(BASE_PATH . '/environment/settings.ini')) {
            $path = BASE_PATH . '/environment/settings.ini';
        } else {
            try {
                $path = app()->envPath('settings.ini');
            } catch (\Throwable) {
                $path = null;
            }
        }

        if (is_string($path) && is_file($path)) {
            clearstatcache(true, $path);
            $settings = parse_ini_file($path, true);
            $section = $settings['realtime'] ?? null;

            return is_array($section) ? $section : [];
        }

        $realtime = env('realtime', []);

        return is_array($realtime) ? $realtime : [];
    }
}

if (!function_exists('moonlight')) {
    /**
     * Moonlight service/action dispatch (sync, async jobs, WebSocket frames).
     */
    #[\NoDiscard]
    function moonlight(): \Pionia\Http\Moonlight\Moonlight
    {
        static $instance = null;

        return $instance ??= new \Pionia\Http\Moonlight\Moonlight();
    }
}

if (!function_exists('serverPort')) {
    /**
     * Resolve the application HTTP port (serve, RoadRunner, frontend API proxy).
     */
    #[\NoDiscard]
    function serverPort(int|string|null|false $cliOverride = null): int
    {
        return (new \Pionia\Http\Server\ServerPortResolver())->resolve($cliOverride);
    }
}

if (!function_exists('defer')) {
    /**
     * Run a closure after the HTTP response is sent to the client.
     *
     * Use this for fire-and-forget post-response work (logging, webhooks, quick cleanup).
     * The closure runs in the **same PHP process** — not a new thread. Long or CPU-heavy
     * work still blocks that worker until it finishes; queue a Moonlight job instead.
     *
     * Requires `react/promise` in the application (`composer require react/promise`).
     *
     * @see async() When you need a Promise or to queue a Moonlight job by service name
     * @see docs/HELPERS.md#background-work-defer--async
     */
    function defer(\Closure $work): void
    {
        \Pionia\Http\Background\Background::assertPromiseSupport();

        $deferred = new \React\Promise\Deferred();
        \Pionia\Http\Background\DeferredWorkBuffer::pushClosure($work, $deferred);
    }
}

if (!function_exists('async')) {
    /**
     * Queue background work to run after the HTTP response (or submit a Moonlight job).
     *
     * **Closure** — same timing as {@see defer()}, but returns a `PromiseInterface` for
     * `.then()` / `.catch()` / {@see await()}. On PHP 8.5+ you must use the return value
     * or cast to `(void)`; prefer {@see defer()} when you do not need a promise.
     *
     * **String service name** — dispatches a Moonlight action. With RoadRunner Jobs enabled,
     * the job is queued on a worker pool; otherwise it runs synchronously after the response.
     *
     * @param \Closure|string $target Closure for post-response work, or Moonlight service name
     * @param string $action Moonlight action when $target is a service name
     * @param array<string, mixed> $payload Moonlight payload
     *
     * @see defer() Fire-and-forget post-response closures (recommended default)
     * @see moonlight()->async() HTTP-shaped 202 response when a job is accepted
     * @see docs/HELPERS.md#background-work-defer--async
     *
     * @return \React\Promise\PromiseInterface
     */
    #[\NoDiscard]
    function async(
        \Closure|string $target,
        string $action = '',
        array $payload = [],
        ?string $switch = null,
    ): \React\Promise\PromiseInterface {
        return \Pionia\Http\Background\Async::dispatch($target, $action, $payload, $switch);
    }
}

if (!function_exists('await')) {
    /**
     * Block until a promise settles. Triggers deferred flush when work is still buffered.
     */
    function await(\React\Promise\PromiseInterface $promise, ?float $timeoutSeconds = null): mixed
    {
        return \Pionia\Http\Background\PromiseAwait::await($promise, $timeoutSeconds);
    }
}

if (!function_exists('promiseCatch')) {
    /**
     * @return \React\Promise\PromiseInterface
     */
    function promiseCatch(
        \React\Promise\PromiseInterface $promise,
        callable $onRejected,
    ): \React\Promise\PromiseInterface {
        return $promise->then(null, $onRejected);
    }
}

if (!function_exists('promiseFinally')) {
    /**
     * @return \React\Promise\PromiseInterface
     */
    function promiseFinally(
        \React\Promise\PromiseInterface $promise,
        callable $onFinally,
    ): \React\Promise\PromiseInterface {
        return $promise->then(
            static function (mixed $value) use ($onFinally) {
                $onFinally();

                return $value;
            },
            static function (\Throwable $reason) use ($onFinally) {
                $onFinally();

                return \React\Promise\reject($reason);
            },
        );
    }
}



if (!function_exists('pionia')) {
    /**
     * @see app()
     */
    #[\NoDiscard]
    function pionia(): AppRealm
    {
        return container();
    }
}

if (!function_exists('envKeys')) {
    /**
     * All environment variable names loaded from `.env`.
     *
     * @return list<string>
     */
    function envKeys(): array
    {
        $key = env('PIONIA_ENV_VARS', env('SYMFONY_DOTENV_VARS', ''));
        if (!is_string($key) || $key === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $key))));
    }
}

if (!function_exists('container_path')){
    function container_path(): string
    {
        if (defined('CONTAINER_PATH')) {
            return CONTAINER_PATH;
        }
        $path = BASE_PATH.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'application.php';
        define('CONTAINER_PATH', $path);
        return $path;
    }
}

if (!function_exists('shouldLogResponses')) {
  /**
   * Whether API responses should be written to the log (see [logging] LOG_RESPONSES).
   */
    function shouldLogResponses(): bool
    {
        $logging = env('logging', []);
        $value = is_array($logging) ? ($logging['LOG_RESPONSES'] ?? false) : false;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}


if (!function_exists('logger')) {
    /**
     * Logger Instance
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    function logger(?string $channel = null): LoggerInterface
    {
        if ($channel !== null) {
            return realm()->get(\Pionia\Logging\LogManager::class)->channel($channel);
        }

        return realm()->get(LoggerInterface::class);
    }
}

/**
 * @deprecated Use {@see timeAgo()} instead.
 */
if (!function_exists('time_ago')){
    #[\Deprecated(message: 'Use timeAgo() instead', since: '3.0')]
    function time_ago(int|float $time): string
    {
        return timeAgo($time);
    }
}

/**
 * Can start from this to interact with  carbon Date
 */
if (!function_exists('now')){
    function now(): DateTime
    {
        return \Pionia\Collections\Carbon::now();
    }
}

/**
 * Human readable time
 */
if (!function_exists('timeAgo')) {
    function timeAgo($datetime): string
    {
        return \Pionia\Collections\Carbon::createFromTimestamp($datetime)->diffForHumans();
    }
}

if (!function_exists('isDebug')) {
    function isDebug(): bool
    {
        return app()->isDebug();
    }
}


if (!function_exists('framework')) {
    /**
     * Get the framework name wherever you're
     * @return string
     */
    function framework(): string
    {
        return app()->getSilently('FRAMEWORK');
    }
}

if (!function_exists('version')) {
    /**
     * Get the version code whereever you're
     * @return string
     */
    function version(): string
    {
        return app()->appVersion ?? \Pionia\Utils\FrameworkVersion::detect();
    }
}

if (!function_exists('frameworkLogo')) {
    /**
     * Get the logo of the framework from anywhere.
     * @return string
     */
    function frameworkLogo()
    {
        return app()->getSilently('FRAMEWORK_ICON');
    }
}

if (!function_exists('frameworkTag')) {
    /**
     * Get the framework description tag anywhere
     * @return string
     */
    function frameworkTag(): string
    {
        return app()->getSilently('FRAMEWORK_DESCRIPTION');
    }
}

if (!function_exists('appName')) {
    /**
     * Get the application name of the initialized application
     * @return string
     */
    function appName(): string
    {
        return app()->getAppName();
    }
}
