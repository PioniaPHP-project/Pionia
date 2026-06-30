<?php

namespace Pionia\Http\Background;

use Closure;
use Pionia\Http\Moonlight\MoonlightJobDispatcher;
use Pionia\Http\Moonlight\MoonlightJobPayload;
use Pionia\Http\Moonlight\MoonlightJobQueue;
use React\Promise\Deferred;

/**
 * Request-scoped buffer for deferred closures and Moonlight jobs.
 */
final class DeferredWorkBuffer
{
    /** @var list<array{closure: Closure, deferred: Deferred}> */
    private static array $closures = [];

    /** @var list<array{job: MoonlightJobPayload, deferred: Deferred}> */
    private static array $jobs = [];

    private static bool $flushed = false;

    public static function pushClosure(Closure $work, Deferred $deferred): void
    {
        self::$closures[] = ['closure' => $work, 'deferred' => $deferred];
        self::$flushed = false;
    }

    public static function pushJob(MoonlightJobPayload $job, Deferred $deferred): void
    {
        self::$jobs[] = ['job' => $job, 'deferred' => $deferred];
        self::$flushed = false;
    }

    public static function isEmpty(): bool
    {
        return self::$closures === [] && self::$jobs === [];
    }

    public static function hasPending(): bool
    {
        return !self::$flushed && !self::isEmpty();
    }

    public static function isFlushed(): bool
    {
        return self::$flushed;
    }

    public static function flush(): void
    {
        if (self::$flushed) {
            return;
        }

        self::$flushed = true;

        foreach (self::$closures as $item) {
            self::runClosure($item['closure'], $item['deferred']);
        }
        self::$closures = [];

        foreach (self::$jobs as $item) {
            self::runJob($item['job'], $item['deferred']);
        }
        self::$jobs = [];
    }

    public static function reset(): void
    {
        self::$closures = [];
        self::$jobs = [];
        self::$flushed = false;
        Background::resetQueueFallbackLog();
    }

    private static function runClosure(Closure $closure, Deferred $deferred): void
    {
        try {
            $deferred->resolve($closure());
        } catch (\Throwable $e) {
            if (\function_exists('logger')) {
                logger()->error('Deferred async closure failed: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }

            $deferred->reject($e);
        }
    }

    private static function runJob(MoonlightJobPayload $job, Deferred $deferred): void
    {
        try {
            if (Background::shouldQueue()) {
                $jobId = MoonlightJobQueue::push($job);

                if ($jobId !== null) {
                    $deferred->resolve(new JobSubmission($jobId));

                    return;
                }

                Background::logQueueFallback();
            }

            $deferred->resolve(MoonlightJobDispatcher::dispatchSync($job));
        } catch (\Throwable $e) {
            if (\function_exists('logger')) {
                logger()->error('Deferred Moonlight job failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'job' => $job->toArray(),
                ]);
            }

            $deferred->reject($e);
        }
    }
}
