<?php

namespace Http\Background;

use Pionia\Http\Background\DeferredWorkBuffer;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;

class BackgroundFlushLifecycleTest extends PioniaTestCase
{
    public function testHandleRequestDoesNotRunDeferredClosure(): void
    {
        $runs = 0;
        (void) async(static function () use (&$runs): void {
            $runs++;
        });

        $this->webApplication()->handleRequest(Request::create(apiPingPath(), 'GET'));

        $this->assertSame(0, $runs);
        $this->assertTrue(DeferredWorkBuffer::hasPending());
    }

    public function testMultipleAsyncCallsInOneRequestAllFlush(): void
    {
        $runs = 0;

        (void) async(static function () use (&$runs): void {
            $runs++;
        });
        (void) async(static function () use (&$runs): void {
            $runs++;
        });
        (void) async(static function () use (&$runs): void {
            $runs++;
        });

        DeferredWorkBuffer::flush();

        $this->assertSame(3, $runs);
    }

    public function testResetBetweenRequestsDoesNotClearBuffer(): void
    {
        $runs = 0;
        (void) async(static function () use (&$runs): void {
            $runs++;
        });

        $this->webApplication()->resetBetweenRequests();
        DeferredWorkBuffer::flush();

        $this->assertSame(1, $runs);
    }
}
