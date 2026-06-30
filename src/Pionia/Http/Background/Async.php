<?php

namespace Pionia\Http\Background;

use Closure;
use Pionia\Http\Moonlight\MoonlightJobPayload;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * Application-facing async() implementation.
 */
final class Async
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function dispatch(
        Closure|string $target,
        string $action = '',
        array $payload = [],
        ?string $switch = null,
    ): PromiseInterface {
        Background::assertPromiseSupport();

        $deferred = new Deferred();

        if ($target instanceof Closure) {
            DeferredWorkBuffer::pushClosure($target, $deferred);

            return $deferred->promise();
        }

        $job = new MoonlightJobPayload(
            $target,
            $action,
            self::stripReservedKeys($payload),
            $switch,
        );

        DeferredWorkBuffer::pushJob($job, $deferred);

        return $deferred->promise();
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
