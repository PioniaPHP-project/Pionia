<?php

/**
 * This authentication backend is auto-generated from pionia cli.
 * Remember to register your backend in index.php.
 */

namespace application\authentications;

use Pionia\Core\Helpers\ContextUserObject;
use Pionia\Core\Interceptions\BaseAuthenticationBackend;
use Pionia\Request\Request;

class JwtAuthBackend extends BaseAuthenticationBackend
{
	/**
	 * Implement this method and return your 'ContextUserObject'. You can use Porm here too!
	 */
	public function authenticate(Request $request): ?ContextUserObject
	{
		$userObj = new ContextUserObject();

		# your logic here...

		return $userObj;
	}
}
