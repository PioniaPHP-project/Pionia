<?php

namespace Pionia\TestSuite\Mocks;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\Middleware;

class MiddlewareMock extends Middleware
{

    /**
     * @inheritDoc
     */
    public function onRequest(Request $request): void
    {
        $request->headers->set('X-Test-Header', 'test-header');
        $request->request->set('test-key', 'test-value');
    }

    /**
     * @inheritDoc
     */
    public function onResponse(Response $response, Request $request): void
    {
        $response->headers->set('X-Test-Header', 'test-Response-header');
        $response->headers->set('Cache-Control', 'public, max-age=3600');
    }
}
