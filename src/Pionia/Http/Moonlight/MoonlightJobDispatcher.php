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
    public static function dispatchSync(MoonlightJobPayload $job, callable $serviceRegistry): BaseResponse
    {
        $request = MoonlightDispatcher::requestFromPayload($job->requestBody());

        return MoonlightDispatcher::dispatch($request, $serviceRegistry);
    }

    /**
     * Queue hook for RoadRunner Jobs — currently runs synchronously until RR Jobs plugin is wired.
     *
     * @param callable(): array<string, mixed>|null $serviceRegistry
     */
    public static function dispatch(MoonlightJobPayload $job, callable $serviceRegistry): BaseResponse
    {
        if (function_exists('logger')) {
            logger()->info('Moonlight job dispatch', $job->toArray());
        }

        return self::dispatchSync($job, $serviceRegistry);
    }
}
