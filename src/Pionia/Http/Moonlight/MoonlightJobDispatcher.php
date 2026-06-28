<?php

namespace Pionia\Http\Moonlight;

use Pionia\Http\Response\BaseResponse;

/**
 * Dispatches Moonlight jobs asynchronously (RoadRunner Jobs integration point).
 */
final class MoonlightJobDispatcher
{
    /**
     * @param callable(): array<string, mixed>|null $serviceRegistry
     */
    public static function dispatchSync(MoonlightJobPayload $job, ?callable $serviceRegistry = null): BaseResponse
    {
        $registry = $serviceRegistry ?? MoonlightRegistry::callable($job->switch);
        $request = MoonlightDispatcher::requestFromPayload($job->requestBody());

        return MoonlightDispatcher::dispatch($request, $registry);
    }

    /**
     * Queue when RoadRunner Jobs is available; otherwise run synchronously.
     */
    public static function dispatch(MoonlightJobPayload $job, ?callable $serviceRegistry = null): BaseResponse
    {
        if (function_exists('logger')) {
            logger()->info('Moonlight job dispatch', $job->toArray());
        }

        if (self::shouldQueue()) {
            $jobId = MoonlightJobQueue::push($job);

            if ($jobId !== null) {
                return response(202, 'Accepted', ['job_id' => $jobId]);
            }
        }

        return self::dispatchSync($job, $serviceRegistry);
    }

    private static function shouldQueue(): bool
    {
        if (!function_exists('moonlightJobsEnabled') || !moonlightJobsEnabled()) {
            return false;
        }

        if (defined('PIONIA_TESTING') && PIONIA_TESTING && !getenv('PIONIA_JOBS_QUEUE')) {
            return false;
        }

        return MoonlightJobQueue::isAvailable();
    }
}
