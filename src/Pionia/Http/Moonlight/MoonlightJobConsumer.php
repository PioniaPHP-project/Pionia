<?php

namespace Pionia\Http\Moonlight;

use Pionia\Base\WebApplication;
use Pionia\Http\Monitoring\RequestMetrics;
use Pionia\Runtime\RuntimeMode;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

/**
 * Consumes Moonlight jobs from RoadRunner and dispatches them through MoonlightDispatcher.
 */
final class MoonlightJobConsumer
{
    public function __construct(
        private readonly WebApplication $app,
    ) {
    }

    public static function isAvailable(): bool
    {
        return class_exists(Consumer::class);
    }

    public function run(): void
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException(
                'RoadRunner Jobs consumer requires spiral/roadrunner-jobs.',
            );
        }

        setRuntimeMode(RuntimeMode::Worker);

        register_shutdown_function(static function (): void {
            if (function_exists('connectionManager')) {
                connectionManager()->disconnect();
            }
        });

        $this->app->bootOnce();

        $consumer = new Consumer();

        while ($task = $consumer->waitTask()) {
            try {
                $this->handleTask($task);
                $task->ack();
            } catch (\Throwable $e) {
                if (function_exists('logger')) {
                    logger()->error('Moonlight job failed: ' . $e->getMessage(), ['exception' => $e]);
                }

                $task->requeue($e);
            } finally {
                RequestMetrics::flush();
                $this->app->resetBetweenRequests();
            }
        }
    }

    private function handleTask(ReceivedTaskInterface $task): void
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($task->getPayload(), true, 512, JSON_THROW_ON_ERROR);
        $job = MoonlightJobPayload::fromArray($payload);

        MoonlightJobDispatcher::dispatchSync($job, MoonlightRegistry::callable($job->switch));
    }
}
