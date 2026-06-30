<?php

namespace Pionia\Middlewares\Builtins;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\Middleware;

class CacheMiddleware extends Middleware
{
    /**
     * @inheritDoc
     */
    public function onRequest(Request $request): void
    {
    }

    /**
     * @param Response $response
     * @param Request $request
     * @inheritDoc
     */
    public function onResponse(Response $response, Request $request): void
    {
    }
}
