<?php

/**
 * This switch is auto-generated from pionia cli.
 */

namespace Application\Switches;

use Pionia\Pionia\Http\Switches\BaseApiServiceSwitch;
use Pionia\Pionia\Utils\Arrayable;

class V2Switch extends BaseApiServiceSwitch
{
	/**
	 * Register services here
	 */
	public function registerServices(): Arrayable
	{
		return arr([
		# Register your services here like `auth=>AuthService::class`
		]);
	}
}
