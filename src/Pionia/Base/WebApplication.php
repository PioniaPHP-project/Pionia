<?php

namespace Pionia\Base;

use Closure;
use DI\DependencyException;
use DI\NotFoundException;
use Pionia\Cache\Cacheable;
use Pionia\Collections\Arrayable;
use Pionia\Contracts\ApplicationContract;
use Pionia\Contracts\ProviderContract;
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
use Symfony\Component\HttpFoundation\BinaryFileResponse;


class WebApplication  implements ApplicationContract
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

    public function __construct(AppRealm $realm)
    {
        $this->realm = $realm;

        $this->booted = false;

        $this->env = container()->env();
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
    public function fly(): Response | BinaryFileResponse
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
