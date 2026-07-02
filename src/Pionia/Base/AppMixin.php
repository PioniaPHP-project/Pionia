<?php

namespace Pionia\Base;

use DIRECTORIES;
use Exception;
use Pionia\Auth\AuthenticationChain;
use Pionia\Collections\Arrayable;
use Pionia\Contracts\ApplicationContract;
use Pionia\Contracts\ProviderContract;
use Pionia\Contracts\CorsContract;
use Pionia\Cors\PioniaCors;
use Pionia\Exceptions\InvalidProviderException;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Logging\LogManager;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Performance\BootstrapCacheGenerator;
use Pionia\Realm\AppRealm;
use Pionia\Utils\ApplicationLifecycleHooks;
use Pionia\Utils\PioniaApplicationType;
use Pionia\Utils\Support;
use Psr\Log\LoggerInterface;

trait AppMixin
{

    use ApplicationLifecycleHooks, BuiltInServices;

    public int $appItemsCacheTTL = 0;

    /**
     * Resolved cached providers are cached,
     * if the provider is not found in caches,
     * then we resolve it as new, and cache for later requests
     * @var Arrayable|null
     */
    public ?Arrayable $unResolvedAppProviders = null;

    /**
     * The booted callbacks
     */
    protected array $bootedCallbacks = [];

    /**
     * These shall be used to run the onBooted and onTermine lifecycle hooks against every provider
     * @var ?Arrayable
     */
    public ?Arrayable $appProviders = null;

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
     * Hooks run after each HTTP request in worker mode (reset state).
     *
     * @var list<\Closure>
     */
    protected array $betweenRequestCallbacks = [];

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Register a callback to run after each request in persistent runtimes.
     */
    public function afterRequest(\Closure $callback): static
    {
        $this->betweenRequestCallbacks[] = $callback;

        return $this;
    }

    public function resetBetweenRequests(): void
    {
        foreach ($this->betweenRequestCallbacks as $callback) {
            $callback($this);
        }
    }
    public function powerUp(?PioniaApplicationType $type = null): ApplicationContract
    {
        if ($this->booted) {
            return $this;
        }

        try {
            $this->realm->set(PioniaApplicationType::class, $this->appType());
            $this->callBootingCallbacks();


            $this->boot_internal();

            // this is where the actual running of the application happens
            $this->registerCorsInstance();
            $this->registerBaseRoutesInstance();
            $this->bootstrapProviderRoutes();

            $this->booted = true;

            $this->callBootedCallbacks();

            return $this;
        } catch (Exception $e) {
            if (realm()->has(LoggerInterface::class)) {
                realm()->getSilently(LoggerInterface::class)->error($e->getMessage());
            }
            $this->shutdown();
        }
    }

    public function boot_internal(): ApplicationContract
    {
        $this->resolveProviders();
        $this->bootstrapMiddlewares();
        $this->bootstrapAuthentications();
        $this->bootstrapCommands();
        $this->bootProviders();
        $this->persistBootstrappedProviders();

        return $this;
    }

    /**
     * Runs configure* hooks and onBooted for every registered provider.
     */
    private function bootProviders(): void
    {
        $this->appProviders?->each(function ($provider) {
            $instance = $this->makeProvider($provider);
            $instance->configureLogging($this->realm()->get(LogManager::class));
            $instance->configureCaching($this->realm()->cache());
            $instance->configureExceptions($this->realm()->exceptions());
            $instance->configureValidations($this->realm()->validations());
            $instance->onBooted();
        });
    }

    private function makeProvider(string $provider): ProviderContract
    {
        return $this->realm()->contextMakeSilently($provider, ['app' => $this]);
    }

    /**
     * Registers all app providers registered in the .ini files that were collected in the env
     * @param bool $considerCached
     * @return WebApplication
     * @throws InvalidProviderException
     */
    protected function resolveProviders(bool $considerCached = true): static
    {
        if ($considerCached) {
            $bootstrapProviders = $this->loadBootstrapProvidersCache();
            if ($bootstrapProviders !== null) {
                $providersArr = arr($bootstrapProviders);
                realm()->set('app_providers', $providersArr);
                $this->appProviders = $providersArr;
                $this->unResolvedAppProviders = $this->calculateUnresolvedProviders() ?? arr([]);

                return $this;
            }

            $providersArr = $this->getCache('app_providers', true);
            if ($providersArr) {
                realm()->set('app_providers', arr($providersArr));
                $this->appProviders = arr($providersArr);
            } else {
                $this->resolveProviders(false);
            }
            $this->unResolvedAppProviders = $this->calculateUnresolvedProviders() ?? arr([]);

            return $this;
        }

        $providers = env()->has('app_providers') ? env()->get('app_providers', []) : [];
        $fineProviders = arr([]);
        arr($providers)->each(function ($value, $key) use ($fineProviders) {
            if (!Support::implements($value, ProviderContract::class)) {
                logger()->warning($value.' is not a valid app provider, therefore skipped.');

                return;
            }
            $fineProviders->add($key, $value);
        });
        $providersArr = $this->builtinProviders()->merge($fineProviders);
        realm()->set('app_providers', $providersArr);
        $this->setCache('app_providers', $providersArr->toArray(), $this->appItemsCacheTTL, true);
        $this->appProviders = $providersArr;
        $this->unResolvedAppProviders = $this->calculateUnresolvedProviders() ?? arr([]);

        return $this;
    }

    /**
     * Collect all commands from the realm registry and merge provider commands.
     */
    private function bootstrapCommands(): void
    {
        $existing = realm()->getSilently(AppRealm::COMMANDS_TAG) ?? [];
        $commands = $existing instanceof Arrayable ? clone $existing : arr((array) $existing);

        if ($this->unResolvedAppProviders?->isFilled()) {
            $commands = $this->bootstrapCommandsFromProviders($commands);
        }

        $this->realm()->set(AppRealm::COMMANDS_TAG, $commands);
    }

    /**
     * @return array<string, string>|null
     */
    private function loadBootstrapProvidersCache(): ?array
    {
        if (!defined('BASE_PATH')) {
            return null;
        }

        return BootstrapCacheGenerator::loadProviders((string) BASE_PATH);
    }

    /**
     * Providers registered in config that have not completed a full boot cycle yet.
     */
    private function calculateUnresolvedProviders(): ?Arrayable
    {
        $registered = $this->builtinProviders()->merge(arr(pionia()->env('app_providers', [])));
        $bootstrapped = arr($this->getCache('bootstrapped_providers') ?? []);

        if ($bootstrapped->isEmpty()) {
            $this->unResolvedAppProviders = $registered;

            return $registered;
        }

        $this->unResolvedAppProviders = $registered->differenceFrom($bootstrapped);

        return $this->unResolvedAppProviders;
    }

    /**
     * Remember which providers finished boot so only newly added packages re-run hooks.
     */
    private function persistBootstrappedProviders(): void
    {
        if (!$this->unResolvedAppProviders?->isFilled()) {
            return;
        }

        $bootstrapped = arr($this->getCache('bootstrapped_providers') ?? []);
        $this->unResolvedAppProviders->each(fn (string $provider) => $bootstrapped->add($provider));
        $this->updateCache('bootstrapped_providers', $bootstrapped->all(), true, $this->appItemsCacheTTL);
    }

    /**
     * Register API switches declared by newly added providers.
     */
    private function bootstrapProviderRoutes(): void
    {
        if (!$this->unResolvedAppProviders?->isFilled()) {
            return;
        }

        $router = router($this->realm());
        $this->resolveRoutesFromProviders($router);
    }

    protected function resolveRoutesFromProviders(PioniaRouter $router): PioniaRouter
    {
        $bootstrapped = arr($this->getCache('bootstrapped_routes') ?? []);
        $this->unResolvedAppProviders?->each(function ($provider) use (&$router, &$bootstrapped) {
            if ($this->providerAlreadyBootstrapped($bootstrapped, $provider)) {
                return;
            }
            $this->makeProvider($provider)->routes($router);
            $bootstrapped->add($provider);
        });
        $this->updateCache('bootstrapped_routes', $bootstrapped->all(), true, $this->appItemsCacheTTL);

        return $router;
    }

    /**
     * Merge middleware from unresolved providers into the realm middleware stack.
     */
    private function bootstrapMiddlewares(): void
    {
        if (!$this->unResolvedAppProviders?->isFilled()) {
            return;
        }

        $chain = new MiddlewareChain();
        $bootstrapped = arr($this->getCache('bootstrapped_middlewares') ?? []);

        $this->unResolvedAppProviders->each(function ($provider) use ($chain, &$bootstrapped) {
            if ($this->providerAlreadyBootstrapped($bootstrapped, $provider)) {
                return;
            }
            $this->makeProvider($provider)->middlewares($chain);
            $bootstrapped->add($provider);
        });

        if ($stack = $chain->middlewareStack()) {
            $this->realm()->set(AppRealm::MIDDLEWARE_TAG, $stack);
            $this->updateCache('app_middlewares', $stack->all(), true, $this->appItemsCacheTTL);
        }

        $this->updateCache('bootstrapped_middlewares', $bootstrapped->all(), true, $this->appItemsCacheTTL);
    }

    /**
     * Add the base routes instance to the context
     * We shall be merging all routes to this instance
     * @return void
     */
    private function registerBaseRoutesInstance(): void
    {
        if (realm()->has(PioniaRouter::class)) {
            return;
        }

        realm()->set(PioniaRouter::class, function () {
            return new PioniaRouter(realm());
        });
    }

    /**
     * Add the cors instance to the context
     */
    private function registerCorsInstance(): void
    {
        realm()->set(PioniaCors::class, static function () {
            return new PioniaCors();
        });
        realm()->set(CorsContract::class, static fn () => realm()->make(PioniaCors::class));
    }

    /**
     * Merge authentications from unresolved providers into the realm auth stack.
     */
    private function bootstrapAuthentications(): void
    {
        if (!$this->unResolvedAppProviders?->isFilled()) {
            return;
        }

        $chain = new AuthenticationChain();
        $bootstrapped = arr($this->getCache('bootstrapped_authentications') ?? []);

        $this->unResolvedAppProviders->each(function ($provider) use ($chain, &$bootstrapped) {
            if ($this->providerAlreadyBootstrapped($bootstrapped, $provider)) {
                return;
            }
            $this->makeProvider($provider)->authentications($chain);
            $bootstrapped->add($provider);
        });

        $this->updateCache('bootstrapped_authentications', $bootstrapped->all(), true, $this->appItemsCacheTTL);
    }

    private function providerAlreadyBootstrapped(Arrayable $bootstrapped, string $provider): bool
    {
        return $bootstrapped->has($provider) || in_array($provider, $bootstrapped->all(), true);
    }


    public function supportedMethods(): array
    {
        return ['POST', 'GET', 'OPTIONS', 'HEADS'];
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

    /**
     * Collect commands from app providers
     * @param Arrayable $commands
     * @return Arrayable
     */
    public function bootstrapCommandsFromProviders(Arrayable $commands): Arrayable
    {
        $bootstrapped = arr($this->getCache('bootstrapped_commands') ?? []);
        $this->unResolvedAppProviders?->each(function ($provider) use (&$commands, &$bootstrapped) {
            if ($this->providerAlreadyBootstrapped($bootstrapped, $provider)) {
                return;
            }
            $commands->merge($this->makeProvider($provider)->commands());
            $bootstrapped->add($provider);
        });
        $this->updateCache('bootstrapped_commands', $bootstrapped->all(), true, $this->appItemsCacheTTL);

        return $commands;
    }

}
