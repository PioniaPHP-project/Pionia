<?php

namespace Pionia\Base\Provider;

use Pionia\Auth\AuthenticationChain;
use Pionia\Base\WebApplication;
use Pionia\Contracts\ProviderContract;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Utils\Microable;

/**
 * Base class for service providers.
 * This class provides a way to register middlewares, authentications, routes and commands
 * to the application from a service provider.
 */
abstract class Provider implements ProviderContract
{
    use Microable;

    protected WebApplication $pionia;

    /**
     * Provider constructor.
     * @param WebApplication $pionia
     */
    public function __construct(WebApplication $pionia)
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

  /**
   * Configure framework logging channels.
   */
    public function configureLogging(\Pionia\Logging\LogManager $log): void {}

  /**
   * Register custom cache stores or replace the default adapter.
   */
    public function configureCaching(\Pionia\Cache\CacheManager $cache): void {}

  /**
   * Customize the exception pipeline.
   */
    public function configureExceptions(\Pionia\Exceptions\ExceptionPipeline $exceptions): void {}
}
