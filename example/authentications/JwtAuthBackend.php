<?php

/**
 * This authentication backend is auto-generated from pionia cli.
 * Remember to register your backend in index.php.
 */

namespace Application\Authentications;

use Pionia\Auth\AuthenticationBackend;
use Pionia\Auth\ContextUserObject;
use Pionia\Http\Request\Request;

class JwtAuthBackend extends AuthenticationBackend
{
	/**
	 * Implement this method and return your 'ContextUserObject'. You can query your database here too!
	 */
	public function authenticate(Request $request): ?ContextUserObject
	{
		$userObj = new ContextUserObject();

		# your logic here...

		return $userObj;
	}
}
