<?php

namespace Pionia\TestSuite\Mocks;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\Middleware;

class MiddlewareMock2 extends Middleware
{

    /**
     * @inheritDoc
     */
    public function onRequest(Request $request): void
    {
        $request->headers->set('X-Test-Request-Header-One', 'test-header-2');
        $request->request->set('test-key', 'test-value-2');
    }

    /**
     * @inheritDoc
     */
    public function onResponse(Response $response): void
    {
        $response->headers->set('X-Test-Response-Header-One', 'test-Response-header-2');
        $response->setCache(['max-age' => 3600, 'public' => true]);
    }
}
