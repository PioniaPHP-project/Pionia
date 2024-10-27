<?php

namespace Pionia\Contracts;

use Pionia\Auth\AuthenticationChain;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Middlewares\MiddlewareChain;

interface ProviderContract
{
    /**
     * Chain your service middlewares to the application's middleware chain.
     */
    public function middlewares(MiddlewareChain $middlewareChain): MiddlewareChain;

    /**
     * Chain your service authentications to the application's authentication chain.
     */
    public function authentications(AuthenticationChain $authenticationChain): AuthenticationChain;

    /**
     * Add your service routes to the application's router system.
     * @param PioniaRouter $router
     * @return PioniaRouter
     */
    public function routes(PioniaRouter $router): PioniaRouter;

    /**
     * Register your service commands to the application's command system.
     */
    public function commands(): array;

    /**
     * Add logic to the application's booted hook.
     */
    public function onBooted(): void;

    /**
     * Add logic to the application's terminating hook.
     */
    public function onTerminate(): void;

}
