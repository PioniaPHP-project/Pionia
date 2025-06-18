<?php

namespace Pionia\Middlewares\Builtins;

use Pionia\Cors\PioniaCors;
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
        realm()->getSilently(PioniaCors::class)->handle($request);
    }

    /**
     * @param Response $response
     * @param Request $request
     * @inheritDoc
     */
    public function onResponse(Response $response, Request $request): void
    {
        realm()->make(PioniaCors::class, ['request' => $request, 'response' => new Response()]);
    }
}
