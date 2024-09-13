<?php

namespace Pionia\Contracts;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\MiddlewareChain;

interface MiddlewareContract
{
    /**
     * This method is called to run the middleware against the incoming request. Every middleware must implement this method.
     *
     * @param Request $request - The request object
     */
    public function onRequest(Request $request);

    /**
     * This method is called to run the middleware against every response. Every middleware must implement this method.
     *
     * @param Response $response - The response object
     */
    public function onResponse(Response $response);

    /**
     * This method is called to run the middleware. Every middleware must implement this method.
     */
    public function execute(Request $request, Response $response, MiddlewareChain $chain): void;
}
