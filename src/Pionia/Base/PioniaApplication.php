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
use Pionia\Cors\PioniaCors;
use Pionia\Events\PioniaEventDispatcher;
use Pionia\Http\Base\WebKernel;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Logging\PioniaLogger;
use Pionia\Middlewares\MiddlewareChain;
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
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
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
     * Framework version
     * @var string
     */
    public string $appVersion = '2.0.0';

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

        $this->dispatcher = $this->getSilently(PioniaEventDispatcher::class) ?? new PioniaEventDispatcher();

        // we set the env to the context
        $this->context->set('aliases', arr([]));

        $this->builtinDirectories()->each(function ($value, $key){
            $this->addAlias($key, $this->appRoot($value));
        });
        $this->contextArrAdd('aliases', $this->builtinNameSpaces()->all());
        // if we passed the environment, we use it, otherwise we get it from the context
        $this->envResolver = $this->getSilently(EnvResolver::class) ?? new EnvResolver($this->getDirFor(DIRECTORIES::ENVIRONMENT_DIR->name));
        $this->env = $this->envResolver->getEnv();
        $this->context->set('env', $this->env);

        $this->resolveLogger();

        // we set the env to the context
        $this->context->set(EnvResolver::class, $this->env);
        // we populate the app name from the env or set it to the default
        $this->context->set('APP_NAME', $this->env->get("APP_NAME") ?? $this->appName);
        // we populate the base directory
        $this->context->set('BASE_DIR', $this->appRoot());

        // we populate the logs directory
        $this->context->set('LOGS_DIR', $this->appRoot($this->env->get('LOGS_DIR') ?? 'logs'));
    }

    /**
     * Set up the context logger
     * @return $this
     */
    private function resolveLogger(): static
    {
        $logger = $this->getSilently(LoggerInterface::class);

        if (!$logger) {
            $logger = new PioniaLogger($this->context);
            $this->context->set(LoggerInterface::class, $logger);
        }
        $this->logger = $logger;
        return $this;
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
        if ($key) {
             $env = arr($_ENV);
             $server = arr($_SERVER);
             if ($env->has($key)){
                 return $env->get($key);
             } elseif ($server->has($key)){
                 return $server->get($key);
             } elseif ($this->env->has($key)){
                 return $this->env->get($key);
             } elseif ($this->contextHas($key)){
                 return $this->getSilently($key);
             }
             return $default;
        }
        return arr($_ENV)->merge($_SERVER);
    }

    /**
     * Check if the application is booted
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function refreshEnv(): void
    {
        $this->envResolver->resolve();
        $this->env = $this->envResolver->getEnv();
        $this->context->set('env', $this->env);
        $this->context->set(EnvResolver::class, $this->env);
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

            // this is where the actual running of the application happens
            $this->bootstrapMiddlewares();
            $this->bootstrapAuthentications();
            $this->bootstrapCommands();
            $this->registerCorsInstance();
            $this->registerBaseRoutesInstance();

            $this->withEndPoints();

            $this->booted = true;

            $this->callBootedCallbacks();

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
            // collect all the middlewares from the environment and the context
            env()->has("commands") && $commands->merge(env('commands'));

            if ($scoped = $this->getSilently('commands')) {
                if ($scoped instanceof Arrayable) {
                    $commands->merge($scoped->all());
                } elseif (is_array($scoped)) {
                    $commands->merge($scoped);
                }
            }

            $commands->merge($this->builtInCommands()->all());
        }

        $this->context->set('commands', $commands);
    }

    /**
     */
    private function bootstrapMiddlewares(): void
    {
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
            $this->setCache('app_middlewares', $middlewares->all(), null, true);
        }

        $this->context->set('middlewares', $middlewares);

        $this->context->set(MiddlewareChain::class, function () {
            return new MiddlewareChain($this);
        });
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
            $this->setCache('app_authentications', $authentications->all(), null, true);
        }

        $this->context->set('authentications', $authentications);

        $this->context->set(AuthenticationChain::class, function () {
            return new AuthenticationChain($this);
        });
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
            $this->setCache("app_routes", $router->getRoutes(), null, true);
        }
        $this->context->set(PioniaRouter::class, $router);
        return $this;
    }
    /**
     * Add a single middleware to the chain
     * @param string $middleware
     * @return $this
     */
    public function addMiddleware(string $middleware): static
    {
        $middlewares = $this->contextHas(MiddlewareChain::class);
        if (!$middlewares) {
           $this->context->set(MiddlewareChain::class, function () {
                return new MiddlewareChain($this);
            });
        }
        $middlewares = $this->getSilently(MiddlewareChain::class);
        $middlewares->add($middleware);
        $this->context->set(MiddlewareChain::class, $middlewares);

        // we need to also add it to the cached middlewares
        $this->hasCache('app_middlewares', true) && $this->deleteCache('app_middlewares', true);

        $this->setCache('app_middlewares', $middlewares->all(), null, true);
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
        $this->hasCache('app_middlewares', true) && $this->deleteCache('app_middlewares', true);

        $this->setCache('app_middlewares', $middlewares->all(), null, true);

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
}
