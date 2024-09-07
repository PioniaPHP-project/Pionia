<?php

namespace Pionia\Pionia\Base;

use Closure;
use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Pionia\Pionia\Auth\AuthenticationChain;
use Pionia\Pionia\Base\Events\PioniaConsoleStarted;
use Pionia\Pionia\Console\BaseCommand;
use Pionia\Pionia\Contracts\ApplicationContract;
use Pionia\Pionia\Cors\PioniaCors;
use Pionia\Pionia\Events\PioniaEventDispatcher;
use Pionia\Pionia\Http\Base\WebKernel;
use Pionia\Pionia\Http\Request\Request;
use Pionia\Pionia\Http\Response\Response;
use Pionia\Pionia\Http\Routing\PioniaRouter;
use Pionia\Pionia\Logging\PioniaLogger;
use Pionia\Pionia\Middlewares\MiddlewareChain;
use Pionia\Pionia\Utils\AppDatabaseHelper;
use Pionia\Pionia\Utils\AppHelpersTrait;
use Pionia\Pionia\Utils\ApplicationLifecycleHooks;
use Pionia\Pionia\Utils\Arrayable;
use Pionia\Pionia\Utils\Containable;
use Pionia\Pionia\Utils\EnvResolver;
use Pionia\Pionia\Utils\Microable;
use Pionia\Pionia\Utils\PathsTrait;
use Pionia\Pionia\Utils\PioniaApplicationType;
use Pionia\Pionia\Utils\Support;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
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

    /**
     * PioniaApplication constructor.
     */
    public function __construct(string $applicationPath = __DIR__)
    {
        if (!defined('BASEPATH')) {
            define('BASEPATH', $applicationPath);
        }

        $this->booted = false;

        parent::__construct($this->appName, $this->appVersion);

        $this->env = new Arrayable();

        $this->context = $container ?? new Container();

        $this->dispatcher = $dispatcher ?? $this->getSilently(PioniaEventDispatcher::class) ?? new PioniaEventDispatcher();

        // we set the env to the context
        $this->context->set('aliases', arr([]));
        $this->builtinDirectories()->each(function ($value, $key){
            $this->addAlias($key, $this->appRoot($value));
        });
        $this->contextArrAdd('aliases', $this->builtinNameSpaces()->all());
        // if we passed the environment, we use it, otherwise we get it from the context
        $this->envResolver = $this->getSilently(EnvResolver::class) ?? new EnvResolver($this->getDirFor(\DIRECTORIES::ENVIRONMENT_DIR->name));
        $this->env = $this->envResolver->getEnv();
        $this->context->set('env', $this->env);


        $logger = $this->getSilently(LoggerInterface::class);

        if (!$logger) {
            $logger = new PioniaLogger($this->context);
            $this->context->set(LoggerInterface::class, $logger);
        }

        $this->setLogger($logger);

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

             if ($default){
                 return $default;
             }
        }
        return arr($_ENV);
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

            // this is where the actual running of the application happens
            $this->bootstrapMiddlewares();
            $this->bootstrapAuthentications();
            $this->bootstrapCommands();
            $this->registerCorsInstance();
            $this->registerBaseRoutesInstance();

            if ($this->applicationType !== PioniaApplicationType::TEST) {
                $this->attemptToConnectToAnyDbAvailable();
            }

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

    /**
     * Collect all the commands from the environment and the context
     */
    private function bootstrapCommands(): void
    {
        $commands = new Arrayable();
        // collect all the middlewares from the environment and the context
//        $this->env->has('commands') && $commands->merge($this->env->get('commands'));
        env()->has("commands") && $commands->merge(env('commands'));

        if ($scoped = $this->getSilently('commands')) {
            if ($scoped instanceof Arrayable) {
                $commands->merge($scoped->all());
            } elseif (is_array($scoped)) {
                $commands->merge($scoped);
            }
        }

        $commands->merge($this->builtInCommands()->all());

        $this->context->set('commands', $commands);
    }

    /**
     */
    private function bootstrapMiddlewares(): void
    {
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

        $this->context->set('middlewares', $middlewares);

        $this->context->set(MiddlewareChain::class, function () {
            return new MiddlewareChain($this);
        });
    }


    /**
     * Adds the collected middlewares to the context
     * @return void
     */
    private function bootstrapAuthentications(): void
    {
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

    public function bootConsole(?string $name = 'Pionia Framework'): PioniaApplication
    {

        $this->applicationType = PioniaApplicationType::CONSOLE;
        // we set the auto exit to false
        $this->setAutoExit(false);

        $this->setName($name);

        $this->setVersion($this->appVersion);

        $this->prepareConsole();

        return $this;
    }


    public function withEndPoints(PioniaRouter $router): PioniaApplication
    {
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
        return $this;
    }

    /**
     * Add middlewares to the application, if a string is passed, it is assumed to be a path to a file relative to the base directory
     * @param Closure $closure
     * @return $this
     */
    public function withMiddlewares(Closure $closure): static
    {
        $middlewares = $closure($this);
        if (!$middlewares instanceof MiddlewareChain) {
            $this->logger->info("The closure passed to `withMiddlewares` must return a MiddlewareChain instance");
            return $this;
        }
        $this->context->set(MiddlewareChain::class, $middlewares);
        $this->context->set('middlewares', $middlewares->all());
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
        $commands = $this->getOrDefault('commands', new Arrayable());

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
        return $this->context
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


}
