<?php

namespace application\services;

use Pionia\Generics\UniversalGenericService;
use Porm\exceptions\BaseDatabaseException;

class DevJobCategoryService extends UniversalGenericService
{
    public string $table = 'dev_job_category';
}
