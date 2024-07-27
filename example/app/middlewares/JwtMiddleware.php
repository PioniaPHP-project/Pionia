<?php

/**
 * This middleware is auto-generated from pionia cli.
 * Remember to register your middleware in index.php.
 */

namespace application\middlewares;

use Pionia\Core\Interceptions\BaseMiddleware;
use Pionia\Request\Request;
use Pionia\Response\Response;

class JwtMiddleware extends BaseMiddleware
{
	/**
	 * Implement the following to add logic on every request and response
	 */
	public function run(Request $request, ?Response $response): void
	{
		if ($response) {

		# your logic against response

		} else {

		# logic against request only

		}
	}
}
