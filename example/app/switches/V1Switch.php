<?php

namespace app\switches;

require __DIR__ . '/../services/DevJobCategoryService.php';

use application\services\DevJobCategoryService;
use Pionia\Core\BaseApiServiceSwitch;

class V1Switch extends BaseApiServiceSwitch
{
    /**
     * @inheritDoc
     */
    protected function registerServices(): array
    {
        return [
            'dev_job_category' => new DevJobCategoryService(),
        ];
    }
}
