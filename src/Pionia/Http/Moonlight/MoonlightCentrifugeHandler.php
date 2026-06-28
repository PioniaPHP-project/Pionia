<?php

namespace Pionia\Http\Moonlight;

use Pionia\Base\WebApplication;

/**
 * RoadRunner Centrifuge plugin integration point (requires roadrunner-php/centrifugo).
 *
 * When installed, handles RPC frames and routes them through {@see MoonlightFrameHandler}.
 */
final class MoonlightCentrifugeHandler
{
    public static function isAvailable(): bool
    {
        return class_exists(\RoadRunner\Centrifugo\CentrifugoWorker::class);
    }

    public static function run(WebApplication $app): void
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException(
                'Centrifugo worker requires roadrunner-php/centrifugo. '
                . 'Install it and enable the centrifuge plugin in .rr.yaml — see AGENTS.md.',
            );
        }

        self::runWithCentrifugo($app);
    }

    private static function runWithCentrifugo(WebApplication $app): void
    {
        /** @var class-string $workerClass */
        $workerClass = \RoadRunner\Centrifugo\CentrifugoWorker::class;
        /** @var class-string $factoryClass */
        $factoryClass = \RoadRunner\Centrifugo\Request\RequestFactory::class;
        /** @var class-string $rpcClass */
        $rpcClass = \RoadRunner\Centrifugo\Request\RPC::class;
        /** @var class-string $rpcResponseClass */
        $rpcResponseClass = \RoadRunner\Centrifugo\Payload\RPCResponse::class;

        setRuntimeMode(\Pionia\Runtime\RuntimeMode::Worker);
        $app->bootOnce();

        $worker = \Spiral\RoadRunner\Worker::create();
        $centrifugoWorker = new $workerClass($worker, new $factoryClass($worker));

        while ($request = $centrifugoWorker->waitRequest()) {
            try {
                if ($request instanceof $rpcClass) {
                    /** @var array<string, mixed> $data */
                    $data = is_array($request->data) ? $request->data : [];
                    $envelope = MoonlightFrameHandler::handle($data);
                    $request->respond(new $rpcResponseClass(data: $envelope));
                }
            } catch (\Throwable $e) {
                if (method_exists($request, 'error')) {
                    $request->error((string) $e->getCode(), $e->getMessage());
                }

                if (function_exists('logger')) {
                    logger()->error('Centrifuge handler error: ' . $e->getMessage(), ['exception' => $e]);
                }
            } finally {
                \Pionia\Http\Monitoring\RequestMetrics::flush();
                $app->resetBetweenRequests();
            }
        }
    }
}
