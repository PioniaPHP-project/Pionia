<?php

namespace Pionia\Core\Interceptions;


use Pionia\Request\Request;
use Pionia\Response\Response;

/**
 * Middleware can run on every request and every response.
 * They have access to every request.
 *
 * You can use these to encrypt
 *
 * Remember when the request has not yet fully in the controller, we have no response yet
 *
 * So before that time, only the request is populated.
 *
 * Also, middlewares run before authentication backends therefore on the request part, they have no access to the authenticated
 * user
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
abstract class BaseMiddleware
{
    /**
     * This method is called to run the middleware. Every middleware must implement this method.
     *
     * @param Request $request - The request object
     * @param Response|null $response - The response object
     */
    public abstract function run(Request $request,  Response | null $response);
}
