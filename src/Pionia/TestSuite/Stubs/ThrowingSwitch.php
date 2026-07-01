<?php

namespace Pionia\TestSuite\Stubs;

use Pionia\Collections\Arrayable;
use Pionia\Http\Switches\ApiSwitch;

class ThrowingSwitch extends ApiSwitch
{
    public static function registerServices(): Arrayable
    {
        return arr([
            'throw' => ThrowingService::class,
        ]);
    }
}
