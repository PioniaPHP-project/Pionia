<?php

namespace Middlewares;

use Application\Middlewares\RequestIdMiddleware;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\TestSuite\PioniaTestCase;

class RequestIdMiddlewareTest extends PioniaTestCase
{
    public function testOnRequestGeneratesIdWhenHeaderMissing(): void
    {
        $middleware = new RequestIdMiddleware();
        $request = Request::create('/api/v1/ping', 'GET');

        $middleware->onRequest($request);

        $this->assertNotEmpty($request->headers->get('X-Request-Id'));
    }

    public function testOnRequestPreservesExistingHeader(): void
    {
        $middleware = new RequestIdMiddleware();
        $request = Request::create('/api/v1/ping', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => 'fixed-id-123',
        ]);

        $middleware->onRequest($request);

        $this->assertSame('fixed-id-123', $request->headers->get('X-Request-Id'));
    }

    public function testOnResponseCopiesRequestIdToResponse(): void
    {
        $middleware = new RequestIdMiddleware();
        $request = Request::create('/api/v1/ping', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => 'trace-abc',
        ]);
        $response = new Response('{}', 200);

        $middleware->onRequest($request);
        $middleware->onResponse($response, $request);

        $this->assertSame('trace-abc', $response->headers->get('X-Request-Id'));
    }
}
