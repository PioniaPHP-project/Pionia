<?php

namespace Http;

use Pionia\Http\Base\WebKernel;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;

class WebKernelExceptionTest extends PioniaTestCase
{
    public function testUnhandledRouteExceptionReturnsJsonEnvelope(): void
    {
        $kernel = new WebKernel();
        $request = Request::create('/this-route-does-not-exist-phase1', 'GET');

        ob_start();
        $response = $kernel->handle($request);
        ob_end_clean();

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThan(0, $payload['returnCode']);
        $this->assertNotEmpty($payload['returnMessage']);
    }
}
