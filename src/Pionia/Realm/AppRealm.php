<?php

namespace Pionia\Realm;

use DI\Container;
use DIRECTORIES;
use Monolog\Logger;
use Pionia\Auth\AuthenticationChain;
use Pionia\Base\BuiltInServices;
use Pionia\Base\EnvResolver;
use Pionia\Base\Pionia;
use Pionia\Base\WebApplication;
use Pionia\Cache\Cacheable;
use Pionia\Cache\CacheManager;
use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Cache\PioniaCache;
use Pionia\Collections\Arrayable;
use Pionia\Events\PioniaEventDispatcher;
use Pionia\Http\Routing\SupportedHttpMethods;
use Pionia\Exceptions\ExceptionPipeline;
use Pionia\Http\Base\WebKernel;
use Pionia\Logging\LogManager;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Porm\ConnectionManager;
use Pionia\Runtime\RuntimeMode;
use Pionia\Templating\TemplateEngine;
use Pionia\Templating\TemplateEngineInterface;
use Pionia\Utils\AppDatabaseHelper;
use Pionia\Utils\PathsTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * We need to separate the DI from the app instance itself
 * Any early boot bindings can be added at this level
 */
class AppRealm implements RealmContract, ContainerInterface
{
    use ContainableRealm, BuiltInServices, PathsTrait, Cacheable, RoutingTrait, AppDatabaseHelper, HandlesExceptions;
    private array $bootingProviders = [];
    private array $bootedProviders = [];
    private bool $resolvingCache = false;
    public const MIDDLEWARE_TAG = 'app.middlewares';
    public const AUTHENTICATIONS_TAG = 'app.authentications';
    public const COMMANDS_TAG = 'app.commands';
    public const ALIASES_TAG = 'app.aliases';
    public const NAMESPACES_TAG = 'app.namespaces';
    public const SERVICES_TAG = 'app.services';
    public const PROVIDERS_TAG = 'app.providers';
    public const DIRS_TAG = 'app.dirs';
    public const CONSOLE_APP_TAG = 'app.console';
    public const WEB_APP_TAG = 'app.http';
    public const APP_PATH_TAG = 'app.path';
    public const APP_ENV_TAG = 'app.env';
    public const APP_ROUTES_TAG = 'app.routes';
    public const SWITCHES_TAGS = 'app.switches';

    public const APP_API_BASE_TAG = 'API_BASE_PATH';
    /**
     * Application name
     * @var string
     */
    public string $appName = 'Pionia Framework';

    /**
     * Framework version
     * @var string
     */
    public string $appVersion = '2.0.2';

    public function __construct(?ContainerInterface $container = new Container())
    {
        $this->context = $container;
        $this->setErrorHandler(GlobalExceptionHandler::class);
    }

    /**
     * Boots up the realm/container
     *
     * Will call both the booting and the booted callbacks if any were added
     *
     * This is done in the `bootstrap/realm.php`
     * @return AppRealm
     */
    public function boot(): static
    {
        $this->resolveBootingProviders();
        $this->set(self::ALIASES_TAG, $this->builtInAliases());
        $this->set(self::APP_API_BASE_TAG, '/api/');

        $this->set(self::CONSOLE_APP_TAG, function (){
            return new Pionia($this);
        });

        $this->set(self::WEB_APP_TAG, function () {
            return new WebApplication($this);
        });

        $this->set(CacheManager::class, fn () => new CacheManager($this));

        $this->context->set(PioniaCache::class, function () {
            $this->resolvingCache = true;
            try {
                return new PioniaCache($this->get(CacheManager::class)->store());
            } finally {
                $this->resolvingCache = false;
            }
        });

        $this->set(EnvResolver::class, function () {
            return new EnvResolver($this->getDirFor(DIRECTORIES::ENVIRONMENT_DIR->name));
        });

        $this->resolveEnv();

        $this->resolveBootedProviders();

        $this->addDefaults();

        return $this;
    }

    /**
     * Runs all providers that don't require much the app context to be available.
     *
     * This should essentially be used to add items to the context/container.
     * Every provider has access to the initialized app container to add whatever it needs at this point
     * @return void
     */
    private function resolveBootingProviders(): void
    {
        foreach ($this->bootingProviders as $provider){
            $provider($this->context);
        }
    }

    /**
     * Adds providers that require access for the core kernel/realm to be ready.
     *
     * The provider has access to both the realm context and the container context.
     *
     * This is the right place to override the defaults or even add more to the defaults.
     * The environment variables are also ready at this point
     *
     * @return void
     */
    private function resolveBootedProviders(): void
    {
        foreach ($this->bootedProviders as $provider){
            $provider($this, $this->context);
        }
    }

    /**
     * The realm instance, useful for the cacheables.
     * @return $this
     */
    private function realm(): static
    {
        return $this;
    }

    public function getAppName(): string
    {
        return $this->getOrDefault('APP_NAME', $this->appName);
    }

    public function getAppPath(): string
    {
        return BASE_PATH;
    }
    /**
     * Collects and sets up all the defaults for the smooth running into the app realm
     * @return void
     */
    protected function addDefaults(): void
    {
        $this->set(self::APP_PATH_TAG, $this->getAppPath());
        $this->set(self::PROVIDERS_TAG, $this->builtinProviders());
        $this->set(self::COMMANDS_TAG, $this->builtInCommands());
        $this->set(self::AUTHENTICATIONS_TAG, $this->builtInAuthentications());
        $this->set(self::MIDDLEWARE_TAG, $this->builtInMiddlewares());
        $this->set(self::DIRS_TAG, $this->builtinDirectories());
        $this->set(self::NAMESPACES_TAG, $this->builtinNameSpaces());
        // force the methods to be those supported by Pionia Application
        $this->set('allowed_methods', [SupportedHttpMethods::POST, SupportedHttpMethods::GET, SupportedHttpMethods::HEAD, SupportedHttpMethods::OPTIONS]);

        $appName = $this->env('APP_NAME', 'Pionia App');
        if ($appName) {
            $this->set('APP_NAME', $appName);
        }

        $this->set('FRAMEWORK', 'Pionia Framework');
        $this->set('FRAMEWORK_ICON', __DIR__.'/favicon.ico');
        $this->set('FRAMEWORK_DESCRIPTION', 'PHP REST Framework for API developers with deadlines.');

        $this->builtinDirectories()->each(function ($value, $key) {
            $this->addAlias($key, $this->appRoot($value));
        });

        $this->set('BASE_DIR', $this->appRoot());
        $this->set('LOGS_DIR', $this->alias(DIRECTORIES::LOGS_DIR->name));
        $this->set('STATIC_DIR', $this->getDirFor(DIRECTORIES::STATIC_DIR->name));

        $this->set('base_logger', function ($name) {
            return new Logger($name);
        });

        $this->set(LogManager::class, fn () => new LogManager($this));

        $this->set(LoggerInterface::class, function () {
            return $this->get(LogManager::class)->channel();
        });

        $this->set(ExceptionPipeline::class, fn () => $this->exceptions());

        $this->set(PioniaEventDispatcher::class, function () {
            return new PioniaEventDispatcher();
        });

        $this->set(TemplateEngineInterface::class, function () {
            return new TemplateEngine();
        });

        $this->set(ConnectionManager::class, new ConnectionManager($this));

        $this->realm()->set(MiddlewareChain::class, function () {
            return new MiddlewareChain();
        });

        $this->set(WebKernel::class, new WebKernel());

        $this->realm()->set(AuthenticationChain::class, function (){
            return new AuthenticationChain();
        });

        $this->resolveEnvArray(self::COMMANDS_TAG)
            ->resolveEnvArray(self::MIDDLEWARE_TAG)
            ->resolveEnvArray(self::ALIASES_TAG)
            ->resolveEnvArray(self::AUTHENTICATIONS_TAG)
            ->resolveEnvArray(self::DIRS_TAG)
            ->resolveEnvArray(self::NAMESPACES_TAG)
            ->resolveEnvArray(self::SERVICES_TAG)
            ->resolveEnvArray(self::PROVIDERS_TAG);

        $this->resolveRoutes()
            ->addDefaultStaticFiles();

        $this->registerExceptionHandlers();
    }

    private function registerExceptionHandlers(): void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (\Throwable $e): void {
            try {
                $response = pionia_handle_exception($e);
                if (PHP_SAPI !== 'cli') {
                    \Pionia\Http\Response\Response::fromEnvelope($response)->send();
                }
            } catch (\Throwable $handlerError) {
                error_log($handlerError->getMessage());
                error_log($e->getMessage());
            }

            if (runtimeMode() !== RuntimeMode::Testing && runtimeMode() !== RuntimeMode::Worker) {
                exit(1);
            }
        });
    }

    /**
     * Reads an item from the env and adds it in both cache and app container
     *
     * @example ```ini
     * [app_middlewares]
     * #...
     *
     * #will add app.middlewares in both the container and cache
     * ```
     * @param $key
     * @return $this
     */
    private function resolveEnvArray($key): static
    {
        $keyClear = str_replace('.', '_', $key);
        $resolved = [];

        if ($this->hasCache($keyClear, true)) {
            $cached = $this->getCache($key, true);
            $resolved = is_object($cached) ? $cached->all() : (array) $cached;
        }

        $fromEnv = $this->envArrayFromResolver($keyClear);
        if ($fromEnv !== []) {
            $resolved = array_merge($resolved, $fromEnv);
        } elseif ($resolved === []) {
            $resolved = $this->env($keyClear, []);
            $resolved = is_object($resolved) ? $resolved->all() : (array) $resolved;
        }

        $this->contextArrAdd($key, $resolved);
        $this->cache($keyClear, $this->getOrDefault($key, []));
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    private function envArrayFromResolver(string $keyClear): array
    {
        $tag = $this->getSilently(self::APP_ENV_TAG);
        if (!$tag?->has($keyClear)) {
            return [];
        }

        $value = $tag->get($keyClear);

        return is_array($value) ? $value : (is_object($value) ? $value->all() : []);
    }

    /**
     * Checks if the app is in debug mode. App can be in debug mode is APP_DEBUG or DEBUG are set to false in the environment
     *
     * You can also access this using just isDebug() anywhere in your app
     * @return bool
     */
    public function isDebug(): bool
    {
        return strtolower(yesNo($this->env('APP_DEBUG'))) == 'yes' || strtolower(yesNo($this->env('DEBUG'))) === 'yes';
    }

    /**
     * Get any alias from the context or all aliases
     * @param string|null $aliasName
     * @param mixed|null $default
     * @return mixed
     */
    public function alias(?string $aliasName = null, mixed $default = null): mixed
    {
        $aliases = $this->getOrDefault(self::ALIASES_TAG, arr([]));
        if (is_array($aliases)) {
            $aliases = arr($aliases);
        }
        if ($aliasName) {
            return $aliases->get($aliasName, $default);
        }

        return $aliases;
    }

    /**
     * Sets the cache adapter the app shall use.
     *
     * The callable must return a {@see CacheAdapterInterface} (PSR-16).
     * For named stores, prefer {@see CacheManager::extend()} from a provider's configureCaching().
     *
     * @param callable(RealmContract, mixed): CacheAdapterInterface $cacheAdaptorResolver
     */
    public function withCacheAdaptor(callable $cacheAdaptorResolver): static
    {
        $adapter = $cacheAdaptorResolver($this, $this->env());
        if ($adapter instanceof CacheAdapterInterface) {
            $this->get(CacheManager::class)->withAdapter($adapter);
            $this->context->set(PioniaCache::class, fn () => new PioniaCache($adapter));
        }

        return $this;
    }

    public function cache(): CacheManager
    {
        return $this->get(CacheManager::class);
    }

    /**
     * Resolves environment variable from all possible sources
     * @return $this
     */
    private function resolveEnv(): static
    {
        $resolver = $this->getOrDefault(EnvResolver::class, new EnvResolver($this->getDirFor(DIRECTORIES::ENVIRONMENT_DIR->name)));
        $this->set(self::APP_ENV_TAG, $resolver->getEnv());
        return $this;
    }

    /**
     * Adds an alias to the context list of aliases
     */
    public function addAlias(string $aliasName, mixed $aliasValue): static
    {
        $this->contextArrAdd(self::ALIASES_TAG, [$aliasName => $aliasValue]);
        return $this;
    }

    /**
     * Initialise an instance of the container from anywhere statically
     * @param string $bootstrapPath This is the folder where our application container resides.
     * @return static
     */
    public static function create(string $bootstrapPath): static
    {
        $path = dirname($bootstrapPath, 1);
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $path);
        }

        if (!defined('CONTAINER_PATH')) {
            define('CONTAINER_PATH', $bootstrapPath.DIRECTORY_SEPARATOR.'routes.php');
        }

        return (new AppRealm())->boot();
    }

    /**
     * Get any variable from the environment or caches
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    public function env(?string $key = null, mixed $default = null): mixed
    {
        if ($key && $this->hasCache($key, true)){
            return $this->getCache($key, true);
        }
        $env = arr($_ENV)->merge($_SERVER);;
        if ($key) {
            if ($env->has($key)){
                return $env->get($key);
            }
            $env = $this->getSilently(self::APP_ENV_TAG);

            if ($env?->has($key)){
                return $env?->get($key);
            }

            if ($this->contextHas($key)){
                return $this->getSilently($key);
            }

            return $default;
        }
        return $env;
    }

    public function refreshEnv(): static
    {
        return $this->resolveEnv();
    }

    /**
     * @param Arrayable|null $env
     */
    public function setEnv(string $key, mixed $env, ?bool $override = true): void
    {
        $resolver = $this->getOrDefault(EnvResolver::class, new EnvResolver($this->getDirFor(DIRECTORIES::ENVIRONMENT_DIR->name)));

        $resolver->dotenv->populate([$key => $env], $override);
        $this->refreshEnv();
    }

    public function cacheInstance(): ?PioniaCache
    {
        if ($this->resolvingCache) {
            return null;
        }

        return $this->getSilently(PioniaCache::class);
    }

    public function event(): PioniaEventDispatcher
    {
        return $this->getSilently(PioniaEventDispatcher::class);
    }


    /**
     * This run requests coming via the CLI
     * @return int
     */
    public function bootConsole(): int
    {
        return $this->make(self::CONSOLE_APP_TAG, ['container' =>$this])->fly();
    }

    /**
     * This runs requests from the browser or over http
     */
    public function bootHttp()
    {
        return $this->make(self::WEB_APP_TAG, ['container' =>$this])->fly();
    }

    function addBootingProvider(callable $callable): static
    {
        $this->bootingProviders[] = $callable;
        return $this;
    }

    function addBootedProvider(callable $callable): static
    {
        $this->bootedProviders[] = $callable;
        return $this;
    }

    function setErrorHandler($handler): static
    {
        $this->set('error_handler', $handler);
        return $this;
    }

    function getErrorHandler()
    {
        return $this->getOrDefault('error_handler', GlobalExceptionHandler::class);
    }
}

