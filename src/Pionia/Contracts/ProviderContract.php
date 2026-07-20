<?php

namespace Pionia\Contracts;

use Pionia\Auth\AuthenticationChain;
use Pionia\Cache\CacheManager;
use Pionia\Exceptions\ExceptionPipeline;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Logging\LogManager;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Validations\ValidationManager;

interface ProviderContract
{
    /**
     * Register middleware on the application stack.
     */
    public function middlewares(MiddlewareChain $middlewareChain): MiddlewareChain;

    /**
     * Register authentication backends on the application stack.
     */
    public function authentications(AuthenticationChain $authenticationChain): AuthenticationChain;

    /**
     * Register API switches on the shared router.
     */
    public function routes(PioniaRouter $router): PioniaRouter;

    /**
     * Register CLI commands (alias => class).
     *
     * @return array<string, class-string>
     */
    public function commands(): array;

    /**
     * Absolute paths to directories containing migration files for this provider.
     *
     * @return list<string>
     */
    public function migrations(): array;

    /**
     * Configure logging channels after the LogManager is ready.
     */
    public function configureLogging(LogManager $log): void;

    /**
     * Register cache stores or replace the default adapter.
     */
    public function configureCaching(CacheManager $cache): void;

    /**
     * Customize the exception pipeline (handlers, maps, reportables).
     */
    public function configureExceptions(ExceptionPipeline $exceptions): void;

    /**
     * Register custom validation rules on the shared ValidationManager.
     */
    public function configureValidations(ValidationManager $validations): void;

    /**
     * Run after middleware, auth, commands, and routes from all providers are registered.
     */
    public function onBooted(): void;

    /**
     * Run during application shutdown (CLI exit, not worker per-request).
     */
    public function onTerminate(): void;
}
