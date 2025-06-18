<?php

/**
 * This switch is auto-generated from pionia cli.
 */

namespace Application\Switches;

use Application\Services\AuthService;
use Application\Services\CategoryService;
use Pionia\Collections\Arrayable;
use Pionia\Http\Switches\BaseApiServiceSwitch;

class MainSwitch extends BaseApiServiceSwitch
{
	/**
	 * Register services here
	 */
	public static function registerServices(): Arrayable
	{
		return arr([
            'auth' => AuthService::class,
            'category'=> CategoryService::class,
		]);
	}
}
