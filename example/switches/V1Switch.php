<?php

namespace Application\Switches;

use Application\Services\Category3Service;
use Application\services\CategoryService;
use Application\Services\SubCategoryService;
use Pionia\Collections\Arrayable;
use Pionia\Http\Switches\BaseApiServiceSwitch;

class V1Switch extends BaseApiServiceSwitch
{
    /**
     * @inheritDoc
     */
    public function registerServices(): Arrayable
    {
        return arr([
            'category' => CategoryService::class,
            'category3' => Category3Service::class,
            'sub_category' => SubCategoryService::class,
        ]);
    }
}
