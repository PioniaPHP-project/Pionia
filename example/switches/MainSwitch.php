<?php

/**
 * This switch is auto-generated from pionia cli.
 */

namespace Application\Switches;

use Application\Services\AuthService;
use Application\Services\CategoryService;
use Application\Services\SampoloService;
use Pionia\Collections\Arrayable;
use Pionia\Http\Switches\BaseApiServiceSwitch;

class MainSwitch extends BaseApiServiceSwitch
{
	/**
	 * Register services here
	 */
	public function registerServices(): Arrayable
	{
		return arr([
		# Register your services here like `auth=>AuthService::class`
            'auth' => AuthService::class,
            'category'=> CategoryService::class,
		]);
	}
}
