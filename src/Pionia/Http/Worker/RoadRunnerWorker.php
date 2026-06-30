<?php

namespace Pionia\Http\Worker;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use Pionia\Base\WebApplication;
use Pionia\Runtime\RuntimeMode;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

/**
 * Persistent HTTP worker loop for RoadRunner.
 *
 * Boot the application once, then handle PSR-7 requests until the worker exits.
 */
class RoadRunnerWorker
{
    public function __construct(
        private readonly WebApplication $app,
    ) {
    }

    public static function isAvailable(): bool
    {
        return class_exists(PSR7Worker::class)
            && class_exists(Psr17Factory::class)
            && class_exists(Psr7Bridge::class);
    }

    public function run(): void
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException(
                'RoadRunner PHP packages are missing. Run: composer require spiral/roadrunner-http nyholm/psr7',
            );
        }

        setRuntimeMode(RuntimeMode::Worker);

        register_shutdown_function(static function (): void {
            if (function_exists('connectionManager')) {
                connectionManager()->disconnect();
            }
        });

        $this->app->bootOnce();

        $worker = Worker::create();
        $factory = new Psr17Factory();
        $psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

        while (true) {
            try {
                $psrRequest = $psr7->waitRequest();
                if ($psrRequest === null) {
                    break;
                }
            } catch (\Throwable) {
                $psr7->respond(new Psr7Response(400));

                continue;
            }

            try {
                $request = Psr7Bridge::toPioniaRequest($psrRequest);
                $response = $this->app->handleRequest($request);
                $psr7->respond(Psr7Bridge::toPsr7Response($response));
                \Pionia\Http\Background\Background::flushDeferredWork();
            } catch (\Throwable $e) {
                if (function_exists('logger')) {
                    logger()->error('RoadRunner worker error: ' . $e->getMessage(), ['exception' => $e]);
                }

                $psr7->respond(new Psr7Response(500, [], 'Internal Server Error'));
                $psr7->getWorker()->error((string) $e);
            }
        }
    }
}
