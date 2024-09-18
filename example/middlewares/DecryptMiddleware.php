<?php

/**
 * This middleware is auto-generated from pionia cli.
 * Remember to register your middleware in bootstrap/application.php or in any ini environment file under [middlewares] section.
 */

namespace Application\Middlewares;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\Middleware;

class DecryptMiddleware extends Middleware
{
	public function onRequest(Request $request)
	{
		# You implementation against incoming request here
	}


	public function onResponse(Response $response)
	{
		# You implementation against every response here
	}
}
