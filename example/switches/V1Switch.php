<?php

namespace Application\Switches;

use Application\services\CategoryService;
use Pionia\Pionia\Http\Switches\BaseApiServiceSwitch;
use Pionia\Pionia\Utils\Arrayable;

class V1Switch extends BaseApiServiceSwitch
{
    /**
     * @inheritDoc
     */
    public function registerServices(): Arrayable
    {
        return arr([
            'category' => CategoryService::class,
        ]);
    }
}