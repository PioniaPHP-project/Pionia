<?php

namespace Pionia\Http\Moonlight;

use Pionia\Http\Response\ApiResponse;

/**
 * Dispatches Moonlight jobs asynchronously (RoadRunner Jobs integration point).
 */
final class MoonlightJobDispatcher
{
    /**
     * @param callable(): array<string, mixed>|null $serviceRegistry
     */
    public static function dispatchSync(MoonlightJobPayload $job, ?callable $serviceRegistry = null): ApiResponse
    {
        $registry = $serviceRegistry ?? MoonlightRegistry::callable($job->switch);
        $request = MoonlightDispatcher::requestFromPayload($job->requestBody());

        return MoonlightDispatcher::dispatch($request, $registry);
    }

    /**
     * Queue when RoadRunner Jobs is available; otherwise run synchronously.
     */
    public static function dispatch(MoonlightJobPayload $job, ?callable $serviceRegistry = null): ApiResponse
    {
        if (function_exists('logger')) {
            logger()->info('Moonlight job dispatch', $job->toArray());
        }

        if (\Pionia\Http\Background\Background::shouldQueue()) {
            $jobId = MoonlightJobQueue::push($job);

            if ($jobId !== null) {
                return response(202, 'Accepted', ['job_id' => $jobId]);
            }
        }

        return self::dispatchSync($job, $serviceRegistry);
    }
}
