<?php

namespace Http\Background;

use Pionia\Http\Background\DeferredWorkBuffer;
use Pionia\Http\Response\BaseResponse;
use Pionia\TestSuite\PioniaTestCase;
use React\Promise\PromiseInterface;

class AsyncHelperTest extends PioniaTestCase
{
    public function testDeferQueuesClosureForFlush(): void
    {
        $runs = 0;
        defer(static function () use (&$runs): void {
            $runs++;
        });

        $this->assertSame(0, $runs);
        $this->assertTrue(DeferredWorkBuffer::hasPending());

        DeferredWorkBuffer::flush();

        $this->assertSame(1, $runs);
    }

    public function testAsyncClosureReturnsPendingPromiseBeforeFlush(): void
    {
        $runs = 0;
        $promise = async(static function () use (&$runs): void {
            $runs++;
        });

        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertSame(0, $runs);
        $this->assertTrue(DeferredWorkBuffer::hasPending());
    }

    public function testAsyncClosureFulfillsOnFlush(): void
    {
        $runs = 0;
        $promise = async(static function () use (&$runs): string {
            $runs++;

            return 'done';
        });

        DeferredWorkBuffer::flush();

        $this->assertSame(1, $runs);
        $this->assertSame('done', await($promise));
    }

    public function testAsyncStringDispatchesMoonlightOnFlush(): void
    {
        $promise = async('auth', 'list_auth');
        $result = await($promise);

        $this->assertInstanceOf(BaseResponse::class, $result);
        $this->assertPioniaOk($result);
    }

    public function testAsyncMailServiceSendWelcome(): void
    {
        $promise = async('mail', 'send_welcome', ['email' => 'demo@example.com']);
        $result = await($promise);

        $this->assertInstanceOf(BaseResponse::class, $result);
        $this->assertPioniaOk($result);
    }

    public function testPromiseCatchHandlesRejection(): void
    {
        $message = null;
        $promise = async(static function (): void {
            throw new \RuntimeException('boom');
        });

        promiseCatch($promise, static function (\Throwable $e) use (&$message): void {
            $message = $e->getMessage();
        });

        DeferredWorkBuffer::flush();

        $this->assertSame('boom', $message);
    }
}
