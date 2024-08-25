<?php

namespace Application\Switches;

use Application\services\CategoryService;
use Pionia\Pionia\Http\Switches\BaseApiServiceSwitch;

class V1Switch extends BaseApiServiceSwitch
{
    /**
     * @inheritDoc
     */
    public function registerServices(): array
    {
        return [
            'category' => CategoryService::class,
        ];
    }
}
