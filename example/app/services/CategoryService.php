<?php

namespace application\services;

use Pionia\Generics\UniversalGenericService;

class CategoryService extends UniversalGenericService
{
    public string $table = 'category';

    public ?array $listColumns = ['id', 'name', 'created_at'];

    public bool $serviceRequiresAuth = false;
}
