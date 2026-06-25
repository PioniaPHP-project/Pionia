<?php

namespace Http;

use Pionia\Http\Base\WebKernel;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;
use Pionia\TestSuite\TestResponse;

class RequestLifecycleTest extends PioniaTestCase
{
    public function testBootingCallbackRunsOnlyOnFirstPowerUp(): void
    {
        $runs = 0;
        $app = $this->webApplication();
        $app->booting(static function () use (&$runs): void {
            $runs++;
        });

        $app->bootOnce();
        $app->bootOnce();

        $this->assertSame(1, $runs);
    }

    public function testBootOnceSkipsRepeatedPowerUp(): void
    {
        $app = $this->webApplication();
        $app->bootOnce();
        $this->assertTrue($app->isBooted());

        $app->bootOnce();
        $this->assertTrue($app->isBooted());
    }

    public function testHandleRequestDoesNotWriteToOutputBuffer(): void
    {
        ob_start();
        $response = $this->webApplication()->handleRequest(Request::create(apiPingPath(), 'GET'));
        $captured = ob_get_clean();

        $this->assertSame('', $captured);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testTwoSequentialRequestsAfterSingleBoot(): void
    {
        $app = $this->webApplication()->bootOnce();

        $first = new TestResponse($app->handleRequest(Request::create(apiPingPath(), 'GET')));
        $second = new TestResponse($app->handleRequest(Request::create(apiPingPath(), 'GET')));

        $this->assertPioniaOk($first);
        $this->assertPioniaOk($second);
    }

    public function testKernelTerminateReturnsWithoutSending(): void
    {
        $kernel = app()->make(WebKernel::class);
        $request = Request::create(apiPingPath(), 'GET');

        ob_start();
        $response = $kernel->handle($request);
        $captured = ob_get_clean();

        $this->assertSame('', $captured);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequestClearsAuthenticationState(): void
    {
        $request = Request::create('/api/v1/', 'POST');
        $request->setAuthenticationContext($this->createMockContextUser());
        $this->assertTrue($request->isAuthenticated());

        $request->clearAuthentication();
        $this->assertFalse($request->isAuthenticated());
        $this->assertNull($request->getAuth());
    }

    public function testAfterRequestCallbacksRunOnResetBetweenRequests(): void
    {
        $runs = 0;
        $app = $this->webApplication();
        $app->afterRequest(static function () use (&$runs): void {
            $runs++;
        });

        $app->resetBetweenRequests();
        $app->resetBetweenRequests();

        $this->assertSame(2, $runs);
    }

    public function testHandleRequestLeavesApplicationBooted(): void
    {
        $app = $this->webApplication();
        $this->assertFalse($app->isBooted());

        $app->handleRequest(Request::create(apiPingPath(), 'GET'));

        $this->assertTrue($app->isBooted());
    }
}
