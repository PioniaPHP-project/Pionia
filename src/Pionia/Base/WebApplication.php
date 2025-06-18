<?php

namespace Pionia\Base;

use Closure;
use DI\DependencyException;
use DI\NotFoundException;
use Pionia\Cache\Cacheable;
use Pionia\Collections\Arrayable;
use Pionia\Contracts\ApplicationContract;
use Pionia\Contracts\ProviderContract;
use Pionia\Exceptions\InvalidProviderException;
use Pionia\Http\Base\WebKernel;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Realm\AppRealm;
use Pionia\Utils\AppDatabaseHelper;
use Pionia\Utils\AppHelpersTrait;
use Pionia\Utils\Microable;
use Pionia\Utils\PioniaApplicationType;
use Pionia\Utils\Support;
use Symfony\Component\Cache\Adapter\Psr16Adapter;


class PioniaApplication  implements ApplicationContract
{
    use AppHelpersTrait,
        Microable,
        AppDatabaseHelper,
        BuiltInServices,
        Cacheable, AppMixin;

    /**
     * Environment variables
     */
    public ?Arrayable $env;

    private AppRealm $realm;

    /**
     * These shall be used to run the onBooted and onTermine lifecycle hooks against every provider
     * @var ?Arrayable
     */
    public ?Arrayable $appProviders = null;

    public function __construct(AppRealm $realm)
    {
        $this->realm = $realm;

        $this->booted = false;

        $this->env = container()->env();
    }

    public function getAppName()
    {
        return env('APP_NAME', $this->appName());
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
        if ($adaptor instanceof Psr16Adapter) {
            container()->set(Psr16Adapter::class, $adaptor);
        }
        return $this;
    }

    public function boot_internal(): PioniaApplication
    {
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
     * @return PioniaApplication
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
     * Get all the environment variables or the value of a single key
     * Will check in the $_ENV, $_SERVER, container, and in the local env array for the same key, other will return all
     * @return PioniaApplicationType
     */
    public function getEnv(?string $key = null, mixed $default = null): mixed
    {
        return container()->env($key, $default);
    }

    public function refreshEnv(): \Pionia\Realm\AppRealm
    {
        return container()->refreshEnv();
    }

    protected function envResolver()
    {
        return container()->get(EnvResolver::class);
    }

    /**
     * @param Arrayable|null $env
     */
    public function setEnv(string $key, mixed $env, ?bool $override = true): void
    {
        $this->envResolver()->dotenv->populate([$key => $env], $override);
        $this->refreshEnv();
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

    public function realm(): \Pionia\Realm\AppRealm
    {
        return realm();
    }

    public function addQueryToPool(string $identifier, string $query): static
    {
        $this->realm()->contextArrAdd('query_pool', [$identifier => $query]);
        return $this;
    }

    protected function report(string $format, string $message, ?array $data = []): void
    {
        $format = strtolower($format);
        $this->logger?->$format($message, $data);
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

        $middlewares = realm()->contextHas(MiddlewareChain::class);
        if (!$middlewares) {
            $this->realm()->set(MiddlewareChain::class, function () {
                return new MiddlewareChain($this);
            });
        }
        $middlewares = $this->realm()->getSilently(MiddlewareChain::class);
        $middlewares->add($middleware);
        realm()->set(MiddlewareChain::class, $middlewares);

//        // we need to also add it to the cached middlewares
        $this->updateCache('app_middlewares', $middlewares->all(), true);
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
            logger()->info("The closure passed to `withMiddlewares` must return a MiddlewareChain instance");
            return $this;
        }
        $this->realm()->set(MiddlewareChain::class, $middlewares);

        $this->realm()->set('middlewares', $middlewares->all());

        // we need to also add it to the cached middlewares
        $this->updateCache('app_middlewares', $middlewares->all(), true, $this->appItemsCacheTTL);
        return $this;
    }


    /**
     * Dispatches an event from anywhere in the application
     * @param object $event
     * @param string $name
     * @return void
     */
    public function dispatch(object $event, string $name): void
    {
        realm()->event()->dispatch($event, $name);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function fly(): Response
    {
        $request = Request::createFromGlobals();
        return $this->powerUp()
            ->make(WebKernel::class)
            ->handle($request);
    }

    /**
     * If set, these shall be the only addresses that can access the application
     * @param array $addresses
     * @return $this
     */
    public function allowedOrigins(array $addresses): static
    {
        $this->realm()->contextArrAdd('allowed_origins', $addresses);
        return $this;
    }

    /**
     * If set, these origins shall be prevented from accessing the application
     * @param array $origins
     * @return $this
     */
    public function blockedOrigins(array $origins): static
    {
        $this->realm()->contextArrAdd('blocked_origins', $origins);
        return $this;
    }

    /**
     * If set, only https requests shall be allowed
     * @return $this
     */
    public function httpsOnly(bool $httpsOnly = true): static
    {
        $this->realm()->set('https_only', $httpsOnly);
        return $this;
    }

    /**
     * Build an entry of the container by its name.
     *
     * This method behaves like resolve() except resolves the entry again every time.
     * For examples if the entry is a class then a new instance will be created each time.
     *
     * This method makes the container behave like a factory.
     *
     *
     **/
    public function make(string $name, array $parameters = []): mixed
    {
        return $this->realm()->make($name, $parameters);
    }

    /**
     * Get any entry from the container by its id
     *
     * This is an acronym of `getOrFail` which throws an exception if the entry is not found
     * @see getOrFail()
     */
    public function resolve(string $id)
    {
        return $this->realm()->get($id);
    }

    /**
     * Register a new provider in the app context(di)
     * Appends the new provider into the existing array of providers
     */
    public function addAppProvider(string $provider): static
    {
        // if we already cached this provider in app providers, we stop here
        if (!Support::implements($provider, ProviderContract::class)){
            logger()->warning($provider .' is not a valid Pionia AppProvider, therefore, skipping.');
            return $this;
        }
        $others = arr($this->env->get('app_providers', []));
        $others->add($provider);
        $this->setEnv('app_providers', $others->all());
        $this->appProviders->add($provider);
        return $this;
    }

    function appType(): PioniaApplicationType
    {
        return PioniaApplicationType::CONSOLE;
    }
}
