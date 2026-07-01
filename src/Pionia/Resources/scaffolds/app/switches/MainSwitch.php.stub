<?php

namespace Application\Switches;

use Application\Services\WelcomeService;
use Pionia\Collections\Arrayable;
use Pionia\Http\Switches\ApiSwitch;

class MainSwitch extends ApiSwitch
{
    public static function registerServices(): Arrayable
    {
        return arr([
            'welcome' => WelcomeService::class,
        ]);
    }
}
