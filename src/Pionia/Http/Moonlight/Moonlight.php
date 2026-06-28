<?php

namespace Pionia\Http\Moonlight;

use Pionia\Http\Response\BaseResponse;

/**
 * Application-facing Moonlight dispatch API (HTTP, async jobs, WebSocket frames).
 */
final class Moonlight
{
    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $service, string $action, array $payload = [], ?string $switch = null): BaseResponse
    {
        return MoonlightDispatcher::dispatchPayload(
            array_merge(['service' => $service, 'action' => $action], self::stripReservedKeys($payload)),
            MoonlightRegistry::callable($switch),
        );
    }

    /**
     * Queue a Moonlight job when RoadRunner Jobs is enabled; otherwise runs synchronously.
     *
     * @param array<string, mixed> $payload
     */
    public function async(string $service, string $action, array $payload = [], ?string $switch = null): BaseResponse
    {
        $job = new MoonlightJobPayload(
            $service,
            $action,
            self::stripReservedKeys($payload),
            $switch,
        );

        return MoonlightJobDispatcher::dispatch($job);
    }

    /**
     * @param array<string, mixed> $frame
     *
     * @return array<string, mixed>
     */
    public function handleFrame(array $frame, ?string $switch = null): array
    {
        return MoonlightFrameHandler::handle($frame, $switch);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function stripReservedKeys(array $payload): array
    {
        unset($payload['service'], $payload['action'], $payload['switch']);

        return $payload;
    }
}
