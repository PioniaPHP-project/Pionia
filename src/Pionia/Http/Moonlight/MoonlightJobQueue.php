<?php

namespace Pionia\Http\Moonlight;

use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Jobs;

/**
 * Pushes Moonlight jobs to a RoadRunner Jobs pipeline.
 */
final class MoonlightJobQueue
{
    public static function isAvailable(): bool
    {
        return class_exists(Jobs::class) && class_exists(RPC::class);
    }

    /**
     * @return non-empty-string|null Job id when queued
     */
    public static function push(MoonlightJobPayload $job, ?string $pipeline = null): ?string
    {
        if (!self::isAvailable() || !moonlightJobsEnabled()) {
            return null;
        }

        $config = jobsConfig();
        $pipeline ??= (string) ($config['PIPELINE'] ?? $config['pipeline'] ?? 'moonlight');
        $rpcAddress = (string) ($config['RPC'] ?? $config['rpc'] ?? 'tcp://127.0.0.1:6001');
        $taskName = (string) ($config['TASK'] ?? $config['task'] ?? 'moonlight.dispatch');

        if ($pipeline === '' || $rpcAddress === '') {
            return null;
        }

        try {
            $jobs = new Jobs(RPC::create($rpcAddress));
            $queue = $jobs->connect($pipeline);
            $payload = json_encode($job->toArray(), JSON_THROW_ON_ERROR);
            $queued = $queue->push($taskName, $payload);

            return $queued->getId();
        } catch (JobsException $e) {
            if (function_exists('logger')) {
                logger()->warning('Moonlight job queue unavailable: ' . $e->getMessage());
            }

            return null;
        } catch (\Throwable $e) {
            if (function_exists('logger')) {
                logger()->warning('Moonlight job queue error', ['exception' => $e]);
            }

            return null;
        }
    }
}
