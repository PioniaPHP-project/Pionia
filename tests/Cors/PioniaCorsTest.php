<?php

namespace Cors;

use Pionia\Cors\PioniaCors;
use Pionia\Http\Base\WebKernel;
use Pionia\Http\Request\Request;
use Pionia\Runtime\RuntimeMode;
use Pionia\TestSuite\PioniaTestCase;
use Pionia\TestSuite\TestResponse;

class PioniaCorsTest extends PioniaTestCase
{
    public function testPreflightReturnsResponseWithCorsHeadersWithoutSending(): void
    {
        $cors = new PioniaCors();
        $request = Request::create('/api/v1/', 'OPTIONS', server: ['HTTP_ORIGIN' => 'http://localhost:4200']);

        ob_start();
        $response = $cors->handle($request);
        $captured = ob_get_clean();

        $this->assertSame('', $captured);
        $this->assertNotNull($response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('http://localhost:4200', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertSame('GET, POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertSame('10', $response->headers->get('Access-Control-Max-Age'));
    }

    public function testApplyToResponseAddsCorsHeaders(): void
    {
        $cors = new PioniaCors();
        $request = Request::create('/api/v1/ping', 'GET', server: ['HTTP_ORIGIN' => 'http://localhost:4200']);
        $response = new \Pionia\Http\Response\Response('ok');

        $cors->applyToResponse($response, $request);

        $this->assertSame('http://localhost:4200', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function testKernelHandlesOptionsPreflightInWorkerModeWithoutOutput(): void
    {
        setRuntimeMode(RuntimeMode::Worker);

        $this->webApplication()->bootOnce();
        $kernel = app()->make(WebKernel::class);
        $request = Request::create('/api/v1/', 'OPTIONS', server: ['HTTP_ORIGIN' => 'http://localhost:4200']);

        ob_start();
        $response = $kernel->handle($request);
        $captured = ob_get_clean();

        $this->assertSame('', $captured);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('http://localhost:4200', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testHandleRequestAppliesCorsHeadersToApiResponse(): void
    {
        $response = new TestResponse(
            $this->webApplication()->handleRequest(
                Request::create(apiPingPath(), 'GET', server: ['HTTP_ORIGIN' => 'http://localhost:4200']),
            ),
        );

        $this->assertPioniaOk($response);
        $this->assertSame('http://localhost:4200', $response->header('Access-Control-Allow-Origin'));
    }

    public function testNonPreflightHandleReturnsNull(): void
    {
        $cors = new PioniaCors();
        $request = Request::create('/api/v1/ping', 'GET');

        $this->assertNull($cors->handle($request));
    }
}
