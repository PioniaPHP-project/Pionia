<?php

namespace Pionia\Base\Provider;

use Pionia\Auth\AuthenticationChain;
use Pionia\Base\PioniaApplication;
use Pionia\Contracts\ProviderContract;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Utils\Microable;

/**
 * Base class for service providers.
 * This class provides a way to register middlewares, authentications, routes and commands
 * to the application from a service provider.
 */
abstract class BaseProvider implements ProviderContract
{
    use Microable;

    protected PioniaApplication $pionia;

    /**
     * BaseProvider constructor.
     * @param PioniaApplication $pionia
     */
    public function __construct(PioniaApplication $pionia)
    {
        $this->pionia = $pionia;
    }

    /**
     * Chain your service middlewares to the application's middleware chain.
     */
    public function middlewares(MiddlewareChain $middlewareChain): MiddlewareChain
    {
        return $middlewareChain;
    }

    /**
     * Chain your service authentications to the application's authentication chain.
     */
    public function authentications(AuthenticationChain $authenticationChain): AuthenticationChain
    {
        return $authenticationChain;
    }

    /**
     * Add your service routes to the application's router system.
     * @param PioniaRouter $router
     * @return PioniaRouter
     */
    public function routes(PioniaRouter $router): PioniaRouter
    {
        return $router;
    }

    /**
     * Register your service commands to the application's command system.
     */
    public function commands(): array
    {
        return [];
    }

    /**
     * Add logic to the application's booted hook.
     */
    public function onBooted(): void {}

    /**
     * Add logic to the application's terminating hook.
     */
    public function onTerminate(): void {}
}
