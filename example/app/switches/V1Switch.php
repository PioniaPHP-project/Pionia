<?php

namespace app\switches;

require __DIR__ . '/../services/CategoryService.php';

use application\services\CategoryService;
use Pionia\Core\BaseApiServiceSwitch;

class V1Switch extends BaseApiServiceSwitch
{
    /**
     * @inheritDoc
     */
    protected function registerServices(): array
    {
        return [
            'category' => CategoryService::class,
        ];
    }
}
