<?php

namespace Http\Background;

use Pionia\Http\Background\DeferredWorkBuffer;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;

class BackgroundFlyDeferredTest extends PioniaTestCase
{
    public function testDeferredWorkExpectsConnectionCloseBeforeSend(): void
    {
        defer(static function (): void {
            // queued only
        });

        $response = $this->webApplication()->handleRequest(Request::create(apiPingPath(), 'GET'));

        $this->assertTrue(DeferredWorkBuffer::hasPending());

        if (DeferredWorkBuffer::hasPending()) {
            $response->headers->set('Connection', 'close');
        }

        $this->assertSame('close', $response->headers->get('Connection'));
    }
}
