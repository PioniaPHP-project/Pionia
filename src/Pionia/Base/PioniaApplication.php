<?php

namespace Pionia\Base;

use Closure;
use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use DIRECTORIES;
use Exception;
use Pionia\Auth\AuthenticationChain;
use Pionia\Base\Events\PioniaConsoleStarted;
use Pionia\Cache\Cacheable;
use Pionia\Cache\PioniaCache;
use Pionia\Cache\PioniaCacheAdaptor;
use Pionia\Collections\Arrayable;
use Pionia\Console\BaseCommand;
use Pionia\Contracts\ApplicationContract;
use Pionia\Contracts\ProviderContract;
use Pionia\Cors\PioniaCors;
use Pionia\Events\PioniaEventDispatcher;
use Pionia\Exceptions\InvalidProviderException;
use Pionia\Http\Base\WebKernel;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Logging\PioniaLogger;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Templating\TemplateEngine;
use Pionia\Templating\TemplateEngineInterface;
use Pionia\Utils\AppDatabaseHelper;
use Pionia\Utils\AppHelpersTrait;
use Pionia\Utils\ApplicationLifecycleHooks;
use Pionia\Utils\Containable;
use Pionia\Utils\Microable;
use Pionia\Utils\PathsTrait;
use Pionia\Utils\PioniaApplicationType;
use Pionia\Utils\Support;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use SebastianBergmann\LinesOfCode\IllogicalValuesException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Process\PhpExecutableFinder;


class PioniaApplication extends Application implements ApplicationContract,  LoggerAwareInterface
{
    use ApplicationLifecycleHooks,
        AppHelpersTrait,
        Microable,
        PathsTrait,
        AppDatabaseHelper,
        BuiltInServices,
        Cacheable,
        Containable;

    /**
     * Application name
     * @var string
     */
    public string $appName = 'Pionia Framework';

    /**
     * App specific cacheacbles' time to live in caches.
     * @var int
     */
    public int $appItemsCacheTTL= 0; // indefinitely

    /**
     * Framework version
     * @var string
     */
    public string $appVersion = '2.0.2';

    /**
     * Application type
     * @var ?PioniaApplicationType
     */
    protected ?PioniaApplicationType $applicationType = PioniaApplicationType::REST;

    /**
     * Environment variables
     * @var ?Arrayable
     */
    public ?Arrayable $env;

    /**
     * These shall be used to run the onBooted and onTermine lifecycle hooks against every provider
     * @var Arrayable
     */
    public Arrayable $appProviders;

    /**
     * Resolved cached providers are cached,
     * if the provider is not found in caches,
     * then we resolve it as new, and cache for later requests
     * @var Arrayable|null
     */
    public ?Arrayable $unResolvedAppProviders = null;

    /**
     * Logger instance
     * @var ?LoggerInterface
     */
    public ?LoggerInterface $logger;

    /**
     * Environment resolver
     * @var ?EnvResolver
     */
    protected ?EnvResolver $envResolver;

    /**
     * Event dispatcher
     * @var ?PioniaEventDispatcher
     */
    public ?PioniaEventDispatcher $dispatcher;

    /**
     * The booted callbacks
     */
    protected array $bootedCallbacks = [];

    /**
     * The terminating callbacks, run before terminating the application
     */
    protected array $terminatingCallbacks = [];
    /**
     * The terminated callbacks, run after terminating the application
     */
    protected array $terminatedCallbacks = [];
    /*
     * Whether the app is fully booted or not
     */
    protected bool $booted = false;

    /**
     * The booting callbacks, callbacks to call before the app runs
     */
    protected array $bootingCallbacks = [];

    protected ?PioniaCacheAdaptor $cacheAdaptor = null;

    /**
     * PioniaApplication constructor.
     */
    public function __construct(string $applicationPath = __DIR__)
    {
        if (!defined('BASEPATH')) {
            define('BASEPATH', $applicationPath);
        }
        $this->context = new Container();

        $this->booted = false;

        parent::__construct($this->appName, $this->appVersion);

        $this->env = new Arrayable();

        $this->appProviders = new Arrayable([]);

        $this->dispatcher = $this->getSilently(PioniaEventDispatcher::class) ?? new PioniaEventDispatcher();

        // we set the env to the context
        $this->context->set('aliases', arr([]));

        $this->context->set(TemplateEngineInterface::class, function () {
            return new TemplateEngine();
        });

        $this->builtinDirectories()->each(function ($value, $key){
            $this->addAlias($key, $this->appRoot($value));
        });
        $this->contextArrAdd('aliases', $this->builtinNameSpaces()->all());
        $this->contextArrAdd('aliases', $this->builtInAliases()->all());
        // if we passed the environment, we use it, otherwise we get it from the context
        $this->envResolver = $this->getSilently(EnvResolver::class) ?? new EnvResolver($this->getDirFor(DIRECTORIES::ENVIRONMENT_DIR->name));
        $this->env = $this->envResolver->getEnv();
        $this->context->set('env', $this->env);

        // we set the env to the context
        $this->context->set(EnvResolver::class, $this->env);
        // we populate the app name from the env or set it to the default
        $this->context->set('APP_NAME', $this->env->get("APP_NAME") ?? $this->appName);
        // we populate the base directory
        $this->context->set('BASE_DIR', $this->appRoot());

        // we populate the logs directory
        $this->context->set('LOGS_DIR', $this->alias(DIRECTORIES::LOGS_DIR->name));

        $this->resolveLogger();
    }

    /**
     * Set up the context logger
     * @return $this
     */
    private function resolveLogger(): static
    {
        $logger = $this->getSilently(LoggerInterface::class);

        if (!$logger) {
            $logger = new PioniaLogger($this);
            $this->context->set(LoggerInterface::class, $logger);
        }
        $this->logger = $logger;
        return $this;
    }

    public function getAppName()
    {
        return env('APP_NAME', $this->appName);
    }

    /**
     * Sets the Cache Adaptor the app shall use hence-forth
     * Defaults to a filesystem adapter
     *
     * All Symfony cache adaptors are supported, even a custom one can be added as long
     * as it supports the PSR-16 CacheInterface
     *
     * The callable receives both the application and the env as arguments
     * @param callable $cacheAdaptorResolver
     * @return $this
     */
    public function withCacheAdaptor(callable $cacheAdaptorResolver): static
    {
        $adaptor = $cacheAdaptorResolver($this, $this->env);
        if ($adaptor instanceof PioniaCacheAdaptor) {
            $this->cacheAdaptor = $adaptor;
        }
        return $this;
    }


    /**
     * Set the default caching adaptor to use
     */
    private function setDefaultCachingAdaptor(): void
    {
        // at this point, we can set the cache instance
        if (!$this->cacheAdaptor) {
            $this->context->set(PioniaCacheAdaptor::class, function () {
                return new FilesystemAdapter(
                    '', 30,
                    $this->alias(DIRECTORIES::CACHE_DIR->name)
                );
            });
        } else {
            $this->context->set(PioniaCacheAdaptor::class, $this->cacheAdaptor);
        }

        $this->context->set(PioniaCache::class, function () {
            return new PioniaCache($this->getSilently(PioniaCacheAdaptor::class));
        });
    }



    /**
     * Get all the environment variables or the value of a single key
     * Will check in the $_ENV, $_SERVER, container, and in the local env array for the same key, other will return all
     * @return PioniaApplicationType
     */
    public function getEnv(?string $key = null, mixed $default = null): mixed
    {
        $env = arr($_ENV)->merge($_SERVER);

        if ($key) {
             if ($env->has($key)){
                 return $env->get($key);
             } elseif ($this->env->has($key)){
                 return $this->env->get($key);
             } elseif ($this->contextHas($key)){
                 return $this->getSilently($key);
             }
             return $default;
        }
        return $env;
    }

    public function refreshEnv(): void
    {
        $this->envResolver->resolve();
        $this->env = $this->envResolver->getEnv();
        $this->context->set('env', $this->env);
        $this->context->set(EnvResolver::class, $this->env);
    }

    /**
     * @param Arrayable|null $env
     */
    public function setEnv(string $key, mixed $env, ?bool $override = true): void
    {
        $this->envResolver->dotenv->populate([$key => $env], $override);
        $this->env->set($key, $env);
    }

    public function welcomePageSettings(): Arrayable
    {
        return arr($this->getEnv('welcome', []));
    }

    /**
     * Check if the application is booted
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Boot the app
     */
    public function powerUp(?PioniaApplicationType $type = null): PioniaApplication
    {
        try {
            if ($type === null) {
                $this->applicationType = $type;
            }

            $this->context->set(PioniaApplicationType::class, $this->applicationType);
            $this->callBootingCallbacks();

            $this->setDefaultCachingAdaptor();

            $this->cacheInstance = $this->getSilently(PioniaCache::class);

            // collect the app providers
            $this->resolveProviders();

            // this is where the actual running of the application happens
            $this->bootstrapMiddlewares();
            $this->bootstrapAuthentications();
            $this->bootstrapCommands();
            $this->registerCorsInstance();
            $this->registerBaseRoutesInstance();

            $this->withEndPoints();

            $this->booted = true;

            $this->callBootedCallbacks();
            $this->bootProviders();
            // we set the app constant to the application so that we can access it globally
            if (!defined('app')) {
                define('app', $this);
            }
            // this runs after the application is booted
            return $this;
        } catch (Exception $e) {
            if ($this->has(LoggerInterface::class)) {
                $this->getSilently(LoggerInterface::class)->error($e->getMessage());
            }
            $this->shutdown();
        }
    }

    /**
     * Runs the boot method of each provider
     * @return void
     */
    private function bootProviders(): void
    {
        $this->appProviders?->each(function ($provider){
            $this->contextMakeSilently($provider, ['app' => $this])->onBooted();
        });
    }

    public function addQueryToPool(string $identifier, string $query): static
    {
        $this->contextArrAdd('query_pool', [$identifier => $query]);
        return $this;
    }

    /**
     * Collect all the commands from the environment and the context
     */
    private function bootstrapCommands(): void
    {
        if ($this->hasCache('app_commands', true)) {
            $commands = Arrayable::toArrayable($this->getCache('app_commands', true));
        } else {
            $commands = new Arrayable();
            // collect all the commands from the environment and the context
            env()->has("commands") && $commands->merge(env('commands'));

            if ($scoped = $this->getSilently('commands')) {
                if ($scoped instanceof Arrayable) {
                    $commands->merge($scoped->all());
                } elseif (is_array($scoped)) {
                    $commands->merge($scoped);
                }
            }
            $commands->merge($this->builtInCommands()->all());
            // register commands from providers too
            if ($this->unResolvedAppProviders?->isFilled()) {
                $commands = $this->bootstrapCommandsFromProviders($commands);
            }
        }

        $this->context->set('commands', $commands);
    }

    /**
     * Collect commands from app providers
     * @param Arrayable $commands
     * @return Arrayable
     */
    public function bootstrapCommandsFromProviders(Arrayable $commands): Arrayable
    {
        $bootstrapped = arr($this->hasCache('bootstrapped_commands') ? $this->getCache('bootstrapped_commands') : []);

        $this->unResolvedAppProviders?->each(function($provider) use (&$commands, &$bootstrapped){
            if (!$this->isCachedIn('bootstrapped_commands', $provider)){
                $providerKlass = new $provider($this);
                $commands->merge($providerKlass->commands());
                $bootstrapped->add($provider);
            }
        });
        $this->updateCache('bootstrapped_commands', $bootstrapped->all(), true, $this->appItemsCacheTTL);
        return $commands;
    }

    /**
     */
    private function bootstrapMiddlewares(): void
    {
        $this->context->set(MiddlewareChain::class, function () {
            return new MiddlewareChain($this);
        });
        $middlewares = null;

        if ($this->hasCache('app_middlewares', true)) {
            $middlewares = Arrayable::toArrayable($this->getCache('app_middlewares', true));
        } else {
            $middlewares = new Arrayable();
            // collect all the middlewares from the environment and the context
//        $this->env->has('middlewares') && $middlewares->merge($this->env->get('middlewares'));
            env()->has("middlewares") && $middlewares->merge(env('middlewares'));

            $scopedMiddlewares = $this->getOrDefault('middlewares', []);

            if ($scopedMiddlewares instanceof Arrayable) {
                $middlewares->merge($scopedMiddlewares->all());
            } elseif (is_array($scopedMiddlewares)) {
                $middlewares->merge($scopedMiddlewares);
            }
            $middlewares->merge($this->builtInMiddlewares()->all());
            $this->setCache('app_middlewares', $middlewares->all(), $this->appItemsCacheTTL, true);
        }

        if ($this->unResolvedAppProviders?->isFilled()) {
            $chain = $this->bootstrapMiddlewaresInProviders($middlewares);
            $middlewares->merge($chain->all());
            $this->updateCache('app_middlewares', $middlewares->all(), true,  $this->appItemsCacheTTL);
        }

        $this->context->set('middlewares', $middlewares);

    }

    /**
     * Passes the middleware chain in the app providers and caches the process
     * @param $middlewares
     * @return MiddlewareChain
     */
    private function bootstrapMiddlewaresInProviders($middlewares): MiddlewareChain
    {
        $bootstrapped = arr($this->getCache('bootstrapped_middlewares') ?? []);
        $chain = new MiddlewareChain($this);
        $chain->addAll($middlewares);
        $this->unResolvedAppProviders?->each(function($provider) use (&$chain, &$bootstrapped){
            $providerKlass = new $provider($this);
            $providerKlass->middlewares($chain);
            $bootstrapped->add($provider);
        });
        $this->updateCache('bootstrapped_middlewares', $bootstrapped, true, $this->appItemsCacheTTL);
        return $chain;
    }


    /**
     * Adds the collected auths to the context
     * Can also cache the authentications for future use
     * @return void
     */
    private function bootstrapAuthentications(): void
    {
        if ($this->hasCache('app_authentications', true)) {
            $authentications = Arrayable::toArrayable($this->getCache('app_authentications', true));
        } else {
            $authentications = new Arrayable();
            // collect all the middlewares from the environment and the context
            $this->env->has('authentications') && $authentications->merge($this->env->get('authentications'));

            $scoped = $this->getOrDefault('authentications', []);

            if ($scoped instanceof Arrayable) {
                $authentications->merge($scoped->all());
            } elseif (is_array($scoped)) {
                $authentications->merge($scoped);
            }
            $authentications->merge($this->builtInAuthentications()->all());
            // cache for future calls
            $this->setCache('app_authentications', $authentications->all(), $this->appItemsCacheTTL, true);
        }

        // bootstrap authentications from providers
        if($this->unResolvedAppProviders?->isFilled()) {
            $chain = $this->bootAuthenticationsInProviders($authentications);
            $authentications->merge($chain->getAuthentications());
            $this->updateCache('app_authentications',  $authentications->all(), true, $this->appItemsCacheTTL);
        }

        $this->context->set('authentications', $authentications);

        $this->context->set(AuthenticationChain::class, function () {
            return new AuthenticationChain($this);
        });
    }

    /**
     * Bootstrap authentications coming from providers. This runs post internal authentications
     * @param $authentications
     * @return AuthenticationChain
     */
    protected function bootAuthenticationsInProviders($authentications): AuthenticationChain
    {
        $chain = new AuthenticationChain($this);
        $chain->addAll($authentications);
        $bootstrapped = arr($this->getCache('bootstrapped_authentications') ?? []);
        $this->unResolvedAppProviders?->each(function ($provider) use (&$chain, &$bootstrapped) {
            $providerKlass = new $provider($this);
            $providerKlass->authentications($chain);
            $bootstrapped->add($provider);
        });
        // cache for later
        $this->updateCache('bootstrapped_authentications', $bootstrapped->all(), true, $this->appItemsCacheTTL);
        return $chain;
    }

    /**
     * Add the cors instance to the context
     * @return void
     */
    private function registerCorsInstance(): void
    {
        $this->context->set(PioniaCors::class, function () {
            return new PioniaCors($this);
        });
    }

    /**
     * Add the base routes instance to the context
     * We shall be merging all routes to this instance
     * @return void
     */
    private function registerBaseRoutesInstance(): void
    {
        if ($this->context->has(PioniaRouter::class)) {
            return;
        }

        $this->context->set(PioniaRouter::class, function () {
            return new PioniaRouter();
        });
    }


    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->context->set(LoggerInterface::class, $logger);
    }

    /**
     * Checks if a certain keyToCheck is set in the cache under a certain keyCached.
     *
     * This is useful if you want to check if a certain items is in a certain cached array or arrayable
     *
     * Outside the app context, you can use `is_cached_in()` to achieve the same.
     * @param string $keyCached
     * @param string $keyToCheck
     * @param bool|null $checkExact
     * @return bool
     */
    public function isCachedIn(string $keyCached, string $keyToCheck, ?bool $checkExact = true): bool
    {
        // if the cache key is not defined at all, we return immediately
        if (!$this->hasCache($keyCached, $checkExact)){
            return false;
        }

        $poolCached = $this->getCache($keyCached, $checkExact);
        // if the value of the cached data is null, we stop here
        if (empty($poolCached)){
            return false;
        }
        // this implies the cached item is the key to check too
        if ($keyCached === $keyToCheck){
            return true;
        }

        // if we cached an array, we check if the array has the keyToCheck
        if (is_array($poolCached) && array_key_exists($keyToCheck, $poolCached)){
            return true;
        }

        // we also check the same if we cached an arrayable
        if ($poolCached instanceof Arrayable && $poolCached->has($keyToCheck)){
            return true;
        }

        // more options can be considered here, but for now we resolve to failing
        return false;
    }

    protected function report(string $format, string $message, ?array $data = []): void
    {
        $format = strtolower($format);
        $this->logger?->$format($message, $data);
    }

    /**
     * @throws Exception
     */
    public function bootConsole(?string $name = 'Pionia Framework'): int
    {
        if (PHP_SAPI !== 'cli') {
            echo 'This script can only be run from the command line.';
            exit(1);
        }

        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            echo 'This script requires PHP 8.1 or later.';
            exit(1);
        }

        $this->powerUp(PioniaApplicationType::CONSOLE);
        // we set the auto exit to false
        $this->setAutoExit(false);

        $this->setName($name);

        $this->setVersion($this->appVersion);

        $this->prepareConsole();

        return $this->run();
    }


    public function withEndPoints(): ?PioniaApplication
    {
        if ($this->hasCache("app_routes", true)) {
            $routes = $this->getCache("app_routes", true);
            $router = new PioniaRouter($routes);
        } else {
            $router = (require $this->alias(DIRECTORIES::BOOTSTRAP_DIR->name) . DIRECTORY_SEPARATOR . 'routes.php');
            // merge all routes from the providers too
            if ($this->unResolvedAppProviders?->isFilled()) {
                $router = $this->resolveRoutesFromProviders($router);
            }
            $this->setCache("app_routes", $router->getRoutes(), $this->appItemsCacheTTL, true);
        }
        $this->context->set(PioniaRouter::class, $router);
        $this->context->set('routes', arr($router->getRoutes()->all()));
        return $this;
    }

    protected function resolveRoutesFromProviders(PioniaRouter $router): PioniaRouter
    {
        $bootstrapped = arr($this->getCache('bootstrapped_routes') ?? []);
        $this->unResolvedAppProviders?->each(function ($provider) use (&$router, &$bootstrapped){
            $providerKlass = new $provider($this);
            $router = $providerKlass->routes($router);
            $bootstrapped->add($provider);
        });
        $this->updateCache('bootstrapped_routes', $bootstrapped->all(), true, $this->appItemsCacheTTL);
        return $router;
    }
    /**
     * Add a single middleware to the chain
     * @param string $middleware
     * @return $this
     */
    public function addMiddleware(string $middleware): static
    {
        // we have already cached this middleware
        if ($this->isCachedIn('app_middlewares', $middleware, true)){
            return $this;
        }

        $middlewares = $this->contextHas(MiddlewareChain::class);
        if (!$middlewares) {
           $this->context->set(MiddlewareChain::class, function () {
                return new MiddlewareChain($this);
            });
        }
        $middlewares = $this->getSilently(MiddlewareChain::class);
        $middlewares->add($middleware);
        $this->context->set(MiddlewareChain::class, $middlewares);

//        // we need to also add it to the cached middlewares
        $this->updateCache('app_middlewares', $middlewares->all(), true, $this->appItemsCacheTTL);
        return $this;
    }

    /**
     * When this is used, the closure passed must return a MiddlewareChain instance
     * Middlewares in this chain will be added to the context and will be the only middlewares used
     * @param Closure $closure
     * @return $this
     */
    public function middlewareChain(Closure $closure): static
    {
        $middlewares = $closure($this);
        if (!$middlewares instanceof MiddlewareChain) {
            $this->logger->info("The closure passed to `withMiddlewares` must return a MiddlewareChain instance");
            return $this;
        }
        $this->context->set(MiddlewareChain::class, $middlewares);

        $this->context->set('middlewares', $middlewares->all());

        // we need to also add it to the cached middlewares
        $this->updateCache('app_middlewares', $middlewares->all(), true, $this->appItemsCacheTTL);
        return $this;
    }

    /**
     * Sync the commands from the configuration and add them to the console application
     */
    public function prepareConsole(): void
    {
        if (! defined('PIONIA_BINARY')) {
            define('PIONIA_BINARY', 'pionia');
        }
        $commands = $this->hasCache('app_commands', true) ? Arrayable::toArrayable($this->getCache('app_commands', true)) : $this->getOrDefault('commands', new Arrayable());

        if ($commands->isFilled()) {
            $commands->each(function (BaseCommand | string $command, $key) {
                if (is_string($command)) {
                    $command = new $command($this, $key);
                }
                $this->add($command);
            });
        }

        $this->dispatch(new PioniaConsoleStarted($this), PioniaConsoleStarted::name());
    }

    /**
     * Get the PHP binary.
     *
     * @return string
     */
    public static function php(): string
    {
        return Support::escapeArgument((new PhpExecutableFinder)->find(false));
    }

    /**
     * Get the pionia cli binary.
     */
    public static function pioniaBinary(): string
    {
        return Support::escapeArgument(defined('PIONIA_BINARY') ? PIONIA_BINARY : 'pionia');
    }

    /**
     * Format the given command as a fully-qualified executable command.
     *
     * @param  string  $string
     * @return string
     */
    public static function formatCommandString(string $string): string
    {
        return sprintf('%s %s %s', self::php(), static::pioniaBinary(), $string);
    }

    /**
     * Dispatches an event from anywhere in the application
     * @param object $event
     * @param string $name
     * @return void
     */
    public function dispatch(object $event, string $name): void
    {
        $this->logger->info("Dispatching event $name");
        $this->dispatcher->dispatch($event, $name);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function handleRequest(): Response
    {
        $request = Request::createFromGlobals();
        return $this->
            powerUp()
            ->make(WebKernel::class, ['application' => $this])
            ->handle($request);
    }

    /**
     * If set, these shall be the only addresses that can access the application
     * @param array $addresses
     * @return $this
     */
    public function allowedOrigins(array $addresses): static
    {
        $this->contextArrAdd('allowed_origins', $addresses);
        return $this;
    }

    /**
     * If set, these origins shall be prevented from accessing the application
     * @param array $origins
     * @return $this
     */
    public function blockedOrigins(array $origins): static
    {
        $this->contextArrAdd('blocked_origins', $origins);
        return $this;
    }

    /**
     * If set, only https requests shall be allowed
     * @return $this
     */
    public function httpsOnly(bool $httpsOnly = true): static
    {
        if ($this->applicationType === PioniaApplicationType::REST) {
            $this->context->set('https_only', $httpsOnly);
            return $this;
        }
        $this->logger->info("The `httpsOnly` method can only be called on a REST application");
        return $this;
    }

    /**
     * Adds an alias to the context list of aliases
     */
    public function addAlias(string $aliasName, mixed $aliasValue): static
    {
        $this->contextArrAdd('aliases', [$aliasName => $aliasValue]);
        return $this;
    }

    /**
     * Get any alias from the context
     * @param string $aliasName
     * @param mixed|null $default
     * @return mixed
     */
    public function alias(string $aliasName, mixed $default = null): mixed
    {
        $aliases =  $this->getOrDefault('aliases', arr([]));
        if (is_array($aliases)){
            $aliases = arr($aliases);
        }
        return $aliases->get($aliasName, $default);
    }

    /**
     * Build an entry of the container by its name.
     *
     * This method behave like resolve() except resolves the entry again every time.
     * For example if the entry is a class then a new instance will be created each time.
     *
     * This method makes the container behave like a factory.
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function make(string $name, array $parameters = []): mixed
    {
        return $this->context->make($name, $parameters);
    }

    /**
     * Get any entry from the container by its id
     *
     * This is an acronym of `getOrFail` which throws an exception if the entry is not found
     * @see getOrFail()
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function resolve(string $id)
    {
        return $this->context->get($id);
    }

    /**
     * Register a new provider in the app context(di)
     * Appends the new provider into the existing array of providers
     * @throws InvalidProviderException
     */
    public function addAppProvider(string $provider): static
    {
        // if we already cached this provider in app providers, we stop here
        if ($this->isCachedIn('app_providers', $provider)){
            return $this;
        }

        if (!Support::implements($provider, ProviderContract::class)){
            throw new InvalidProviderException($provider .' is not a valid Pionia AppProvider');
        }
        // we add it in the cached providers
        if($this->hasCache('app_providers')){
            $cachedProviders = $this->getCache('app_providers', true);
            if ($cachedProviders instanceof Arrayable){
                $cachedProviders->add($provider);
                $this->updateCache('app_providers', $cachedProviders, true, $this->appItemsCacheTTL);
            } else if (is_array($cachedProviders)){
                $cachedProviders = array_merge($cachedProviders, [$provider => $provider]);
                $this->updateCache('app_providers', $cachedProviders, true, $this->appItemsCacheTTL);
            } else {
                throw new InvalidProviderException("Invalid cached app providers");
            }
        }
        return $this;
    }

    /**
     * we only want to start resolving only new providers
     * @return Arrayable|null
     */
    private function calculateUnresolvedProviders(): ?Arrayable
    {
        $cached = arr($this->getCache('app_providers') ?? []);
        $envProvided = arr($this->getEnv('app_providers', []));
        $builtIns = $this->builtinProviders();
        $all = $builtIns->merge($envProvided);
        if ($cached->isEmpty()){
            return $all;
        }
        if ($all->isEmpty()){
            return arr([]);
        }
        $this->unResolvedAppProviders = $all->differenceFrom($cached);
        return $this->unResolvedAppProviders;
    }

    /**
     * Registers all app providers registered in the .ini files that were collected in the env
     * @param bool $considerCached
     * @return PioniaApplication
     * @throws InvalidProviderException
     */
    protected function resolveProviders(bool $considerCached = true): static
    {
        if ($considerCached){
            $providersArr = $this->getCache("app_providers", true);
            if ($providersArr){
                $this->set("app_providers", arr($providersArr));
                $this->appProviders = $providersArr;
            } else {
                // if we have no cached providers, then
                $this->resolveProviders(false);
            }
            $this->calculateUnresolvedProviders();
            return $this;
        }
        // we only come here if our providers weren't cached already
        // here we re-collect them from the config
        $providers= $this->env->has('app_providers') ? $this->env->get('app_providers', []) : [];
        $fineProviders = arr([]);
        arr($providers)->each(function ($value, $key) use ($fineProviders) {
            if (!Support::implements($value, ProviderContract::class)){
                $this->logger->warning($value.' is not a valid app provider, therefore skipped.');
            }
            $fineProviders->add($key, $value);
        });
        $providersArr = $this->builtinProviders()->merge($fineProviders);
        if ($providersArr->isFilled()){
            $this->set("app_providers", $providersArr);
            $this->setCache("app_providers", $providersArr->toArray(), $this->appItemsCacheTTL, true);
            $this->appProviders = $providersArr;
            $this->unResolvedAppProviders = $this->calculateUnresolvedProviders();
        }
        return $this;
    }
}
