<?php

namespace Pionia\TestSuite\Stubs;

use Pionia\Auth\AuthenticationChain;
use Pionia\Base\Provider\Provider;
use Pionia\Cache\CacheManager;
use Pionia\Exceptions\ExceptionPipeline;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Logging\LogManager;
use Pionia\Middlewares\MiddlewareChain;

/**
 * Test double that records which provider hooks ran during boot.
 */
final class RecordingProvider extends Provider
{
    /** @var list<string> */
    public static array $bootSteps = [];

    public static function reset(): void
    {
        self::$bootSteps = [];
    }

    public function middlewares(MiddlewareChain $middlewareChain): MiddlewareChain
    {
        self::$bootSteps[] = 'middlewares';

        return $middlewareChain;
    }

    public function authentications(AuthenticationChain $authenticationChain): AuthenticationChain
    {
        self::$bootSteps[] = 'authentications';

        return $authenticationChain;
    }

    public function routes(PioniaRouter $router): PioniaRouter
    {
        self::$bootSteps[] = 'routes';

        return $router;
    }

    public function commands(): array
    {
        self::$bootSteps[] = 'commands';

        return ['recording_ping' => RecordingPingCommand::class];
    }

    public function configureLogging(LogManager $log): void
    {
        self::$bootSteps[] = 'configureLogging';
    }

    public function configureCaching(CacheManager $cache): void
    {
        self::$bootSteps[] = 'configureCaching';
    }

    public function configureExceptions(ExceptionPipeline $exceptions): void
    {
        self::$bootSteps[] = 'configureExceptions';
    }

    public function onBooted(): void
    {
        self::$bootSteps[] = 'onBooted';
    }
}
