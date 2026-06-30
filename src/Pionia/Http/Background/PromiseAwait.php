<?php

namespace Pionia\Http\Background;

use React\Promise\PromiseInterface;

/**
 * Block until a Promise settles (CLI/tests). Triggers deferred flush when needed.
 */
final class PromiseAwait
{
    public static function await(PromiseInterface $promise, ?float $timeoutSeconds = null): mixed
    {
        if (DeferredWorkBuffer::hasPending()) {
            Background::flushDeferredWork();
        }

        $settled = false;
        $value = null;
        $error = null;

        $promise->then(
            static function (mixed $resolved) use (&$settled, &$value): void {
                $value = $resolved;
                $settled = true;
            },
            static function (\Throwable $reason) use (&$settled, &$error): void {
                $error = $reason;
                $settled = true;
            },
        );

        if ($settled) {
            if ($error instanceof \Throwable) {
                throw $error;
            }

            return $value;
        }

        $deadline = $timeoutSeconds === null
            ? null
            : microtime(true) + $timeoutSeconds;

        while (!$settled) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                throw new \RuntimeException('await() timed out waiting for promise to settle');
            }

            usleep(1_000);
        }

        if ($error instanceof \Throwable) {
            throw $error;
        }

        return $value;
    }
}
