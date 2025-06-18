<?php

namespace Pionia\Base;

use DIRECTORIES;
use Exception;
use Pionia\Auth\AuthenticationChain;
use Pionia\Collections\Arrayable;
use Pionia\Contracts\ApplicationContract;
use Pionia\Contracts\ProviderContract;
use Pionia\Cors\PioniaCors;
use Pionia\Exceptions\InvalidProviderException;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Middlewares\MiddlewareChain;
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
     * Boot the app
     */
    public function powerUp(?PioniaApplicationType $type = null): ApplicationContract
    {
        try {
            $this->realm->set(PioniaApplicationType::class, $this->appType());
            $this->callBootingCallbacks();


            $this->boot_internal();

            // this is where the actual running of the application happens
            $this->registerCorsInstance();
            $this->registerBaseRoutesInstance();

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
        $this->bootstrapCommands();
        // collect the app providers
        $this->resolveProviders();
        $this->bootProviders();
        return $this;
    }

    /**
     * Runs the boot method of each provider
     * @return void
     */
    private function bootProviders(): void
    {
        $this->appProviders?->each(function ($provider){
            $this->realm()->contextMakeSilently($provider, ['app' => $this])->onBooted();
        });
    }

    /**
     * Registers all app providers registered in the .ini files that were collected in the env
     * @param bool $considerCached
     * @return WebApplication
     * @throws InvalidProviderException
     */
    protected function resolveProviders(bool $considerCached = true): static
    {
        if ($considerCached){
            $providersArr = $this->getCache("app_providers", true);
            if ($providersArr){
                realm()->set("app_providers", arr($providersArr));
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
        $providers= env()->has('app_providers') ? env()->get('app_providers', []) : [];
        $fineProviders = arr([]);
        arr($providers)->each(function ($value, $key) use ($fineProviders) {
            if (!Support::implements($value, ProviderContract::class)){
                logger()->warning($value.' is not a valid app provider, therefore skipped.');
            }
            $fineProviders->add($key, $value);
        });
        $providersArr = $this->builtinProviders()->merge($fineProviders);
        if ($providersArr->isFilled()){
            realm()->set("app_providers", $providersArr);
            $this->setCache("app_providers", $providersArr->toArray(), $this->appItemsCacheTTL, true);
            $this->appProviders = $providersArr;
            $this->unResolvedAppProviders = $this->calculateUnresolvedProviders();
        }
        return $this;
    }

    /**
     * Collect all the commands from the environment and the context
     */
    private function bootstrapCommands(): void
    {
        if ($this->hasCache(realm()::COMMANDS_TAG, true)) {
            $commands = Arrayable::toArrayable($this->getCache(realm()::COMMANDS_TAG, true));
        } else {
            $commands = new Arrayable();
            // collect all the commands from the environment and the context

            if ($scoped = realm()->getSilently(realm()::COMMANDS_TAG)) {
                $commands->merge($scoped);
            }
            // register commands from providers too
            if ($this->unResolvedAppProviders?->isFilled()) {
                $commands = $this->bootstrapCommandsFromProviders($commands);
            }
        }

        $this->realm()->contextArrAdd($this->realm::COMMANDS_TAG, $commands->all());
    }

//    public function withEndPoints(): ?ApplicationContract
//    {
//        if ($this->hasCache("app_routes", true)) {
//            $routes = $this->getCache("app_routes", true);
//            $router = new PioniaRouter($routes);
//        } else {
//            dd(app()->appRoot(realm()->alias(DIRECTORIES::BOOTSTRAP_DIR->name)  . 'routes.php'));
//            $router = realm()->appRoot(require realm()->alias(DIRECTORIES::BOOTSTRAP_DIR->name) . DIRECTORY_SEPARATOR . 'routes.php');
//            // merge all routes from the providers too
//            if ($this->unResolvedAppProviders?->isFilled()) {
//                $router = $this->resolveRoutesFromProviders($router);
//            }
//            $this->setCache("app_routes", $router->getRoutes(), $this->appItemsCacheTTL, true);
//        }
//        $this->realm()->set(PioniaRouter::class, $router);
//        $this->realm()->set('routes', arr($router->getRoutes()->all()));
//        return $this;
//    }

    /**
     * we only want to start resolving only new providers
     * @return Arrayable|null
     */
    private function calculateUnresolvedProviders(): ?Arrayable
    {
        $cached = arr($this->getCache('app_providers') ?? []);
        $envProvided = arr(pionia()->env('app_providers', []));
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
     */
    private function bootstrapMiddlewares(): void
    {
        $middlewares = null;

        if ($this->hasCache('app_middlewares', true)) {
            $middlewares = arr($this->getCache('app_middlewares', true));
        } else {
            $middlewares = new Arrayable();
            // collect all the middlewares from the environment and the context
//        $this->env->has('middlewares') && $middlewares->merge($this->env->get('middlewares'));
            env()->has("middlewares") && $middlewares->merge(env('middlewares'));

            $scopedMiddlewares = realm()->getOrDefault('middlewares', []);

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

        $this->realm()->set('middlewares', $middlewares);

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
            return new PioniaRouter();
        });
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
     * Add the cors instance to the context
     * @return void
     */
    private function registerCorsInstance(): void
    {
        realm()->set(PioniaCors::class, function () {
            return new PioniaCors();
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
            env()->has('authentications') && $authentications->merge(env('authentications'));

            $scoped = realm()->getOrDefault('authentications', []);

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

        $this->realm()->set('authentications', $authentications);

        $this->realm()->set(AuthenticationChain::class, function () {
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
        $bootstrapped = arr($this->getCache(realm()::COMMANDS_TAG) ?? []);
        $this->unResolvedAppProviders?->each(function($provider) use (&$commands, &$bootstrapped){
            if (!$this->isCachedIn(realm()::COMMANDS_TAG, $provider)){
                $providerKlass = new $provider($this);
                $commands->merge($providerKlass->commands());
                $bootstrapped->add($provider);
            }
        });
        $this->updateCache(realm()::COMMANDS_TAG, $bootstrapped->all(), true, $this->appItemsCacheTTL);
        return $commands;
    }

}
