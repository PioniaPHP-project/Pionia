<?php

namespace Pionia\TestSuite\Stubs;

use Pionia\Collections\Arrayable;
use Pionia\Http\Switches\BaseApiServiceSwitch;

class ThrowingSwitch extends BaseApiServiceSwitch
{
    public static function registerServices(): Arrayable
    {
        return arr([
            'throw' => ThrowingService::class,
        ]);
    }
}
