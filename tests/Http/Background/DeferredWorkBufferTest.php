<?php

namespace Http\Background;

use Pionia\Http\Background\DeferredWorkBuffer;
use Pionia\TestSuite\PioniaTestCase;
use React\Promise\Deferred;

class DeferredWorkBufferTest extends PioniaTestCase
{
    public function testStartsEmpty(): void
    {
        $this->assertTrue(DeferredWorkBuffer::isEmpty());
        $this->assertFalse(DeferredWorkBuffer::isFlushed());
    }

    public function testPushClosureDoesNotInvokeImmediately(): void
    {
        $runs = 0;
        $deferred = new Deferred();

        DeferredWorkBuffer::pushClosure(static function () use (&$runs): string {
            $runs++;

            return 'ok';
        }, $deferred);

        $this->assertSame(0, $runs);
        $this->assertFalse(DeferredWorkBuffer::isFlushed());
    }

    public function testFlushInvokesClosuresInOrder(): void
    {
        $order = [];
        $first = new Deferred();
        $second = new Deferred();

        DeferredWorkBuffer::pushClosure(static function () use (&$order): void {
            $order[] = 1;
        }, $first);
        DeferredWorkBuffer::pushClosure(static function () use (&$order): void {
            $order[] = 2;
        }, $second);

        DeferredWorkBuffer::flush();

        $this->assertSame([1, 2], $order);
    }

    public function testFlushSettlesLinkedPromiseWithReturnValue(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        DeferredWorkBuffer::pushClosure(static fn (): string => 'sent', $deferred);
        DeferredWorkBuffer::flush();

        $value = null;
        $promise->then(static function (mixed $resolved) use (&$value): void {
            $value = $resolved;
        });

        $this->assertSame('sent', $value);
    }

    public function testFlushRejectsPromiseOnClosureException(): void
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();
        $error = null;

        DeferredWorkBuffer::pushClosure(static function (): void {
            throw new \RuntimeException('mail failed');
        }, $deferred);

        DeferredWorkBuffer::flush();

        $promise->then(null, static function (\Throwable $reason) use (&$error): void {
            $error = $reason;
        });

        $this->assertInstanceOf(\RuntimeException::class, $error);
        $this->assertSame('mail failed', $error->getMessage());
    }

    public function testFlushClearsBuffer(): void
    {
        $deferred = new Deferred();
        DeferredWorkBuffer::pushClosure(static fn (): string => 'x', $deferred);
        DeferredWorkBuffer::flush();

        $this->assertTrue(DeferredWorkBuffer::isEmpty());
        $this->assertTrue(DeferredWorkBuffer::isFlushed());
    }

    public function testSecondFlushIsNoOp(): void
    {
        $runs = 0;
        $deferred = new Deferred();

        DeferredWorkBuffer::pushClosure(static function () use (&$runs): void {
            $runs++;
        }, $deferred);

        DeferredWorkBuffer::flush();
        DeferredWorkBuffer::flush();

        $this->assertSame(1, $runs);
    }
}
