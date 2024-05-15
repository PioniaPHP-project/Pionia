<?php

namespace Pionia\core\interceptions;


use Pionia\request\Request;
use Pionia\response\Response;

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
    public abstract function run(Request $request,  Response | null $response);
}
