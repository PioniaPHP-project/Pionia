<?php

namespace app\switches;

require __DIR__ . '/../services/CategoryService.php';
require __DIR__ . '/../services/BlogService.php';

use application\services\BlogService;
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
            'article' => BlogService::class,
        ];
    }
}
