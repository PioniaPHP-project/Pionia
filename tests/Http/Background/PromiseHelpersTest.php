<?php

namespace Http\Background;

use Pionia\Http\Background\DeferredWorkBuffer;
use Pionia\TestSuite\PioniaTestCase;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\resolve;

class PromiseHelpersTest extends PioniaTestCase
{
    public function testAwaitResolvesFulfilledPromise(): void
    {
        $this->assertSame(42, await(resolve(42)));
    }

    public function testAwaitTriggersFlushForPendingDeferredPromise(): void
    {
        $runs = 0;
        $promise = async(static function () use (&$runs): int {
            $runs++;

            return 7;
        });

        $this->assertSame(7, await($promise));
        $this->assertSame(1, $runs);
    }

    public function testPromiseFinallyRunsOnSuccess(): void
    {
        $finallyRuns = 0;
        $promise = promiseFinally(resolve('ok'), static function () use (&$finallyRuns): void {
            $finallyRuns++;
        });

        await($promise);

        $this->assertSame(1, $finallyRuns);
    }

    public function testPromiseFinallyRunsOnFailure(): void
    {
        $finallyRuns = 0;
        $promise = promiseFinally(async(static function (): void {
            throw new \RuntimeException('fail');
        }), static function () use (&$finallyRuns): void {
            $finallyRuns++;
        });

        promiseCatch($promise, static function (): void {
        });

        try {
            await($promise);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('fail', $e->getMessage());
        }

        $this->assertSame(1, $finallyRuns);
    }

    public function testPromiseAllWaitsForMultipleAsyncJobs(): void
    {
        $combined = all([
            async('auth', 'list_auth'),
            async('mail', 'send_welcome', ['email' => 'a@b.com']),
        ]);

        $results = await($combined);

        $this->assertCount(2, $results);
        $this->assertPioniaOk($results[0]);
        $this->assertPioniaOk($results[1]);
    }
}
