<?php

namespace Pionia\Pionia\Contracts;

use Pionia\Pionia\Interceptors\Middlewares\MiddlewareChain;
use Pionia\Request\Request;
use Pionia\Response\Response;

interface MiddlewareInterface
{
    /**
     * This method is called to run the middleware. Every middleware must implement this method.
     *
     * @param Request $request - The request object
     */
    public function onRequest(Request $request);

    /**
     * This method is called to run the middleware. Every middleware must implement this method.
     *
     * @param Response $response - The response object
     */
    public function onResponse(Response $response);

    /**
     * This method is called before the middleware runs against the request.
     */
    public function execute(Request $request, Response $response, MiddlewareChain $chain): void;
}
