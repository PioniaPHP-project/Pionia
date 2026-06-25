<?php

namespace Application\Middlewares;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\Middleware;

/**
 * Adds X-Request-Id to every request/response for tracing in logs.
 */
class RequestIdMiddleware extends Middleware
{
    public function onRequest(Request $request): void
    {
        $id = $request->headers->get('X-Request-Id') ?: bin2hex(random_bytes(8));
        $request->headers->set('X-Request-Id', $id);
    }

    public function onResponse(Response $response, Request $request): void
    {
        $id = $request->headers->get('X-Request-Id');
        if ($id) {
            $response->headers->set('X-Request-Id', $id);
        }
    }
}
