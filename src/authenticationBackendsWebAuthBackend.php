<?php

/**
 * This authentication backend is auto-generated from pionia cli.
 * Remember to register your backend in index.php.
 */

namespace application\authenticationBackends;

use Pionia\core\helpers\ContextUserObject;
use Pionia\core\interceptions\BaseAuthenticationBackend;
use Pionia\request\Request;

class WebAuthBackend extends BaseAuthenticationBackend
{
	/**
	 * Implement this method and return your 'ContextUserObject'. You can use Porm here too!
	 */
	public function authenticate(Request $request): ContextUserObject
	{
		$userObj = new ContextUserObject();

		# your logic here...

		return $userObj;
	}
}
