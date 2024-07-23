<?php

namespace application\services;

use Pionia\Generics\Base\JoinType;
use Pionia\Generics\UniversalGenericService;

class CategoryService extends UniversalGenericService
{
    public string $table = 'category';

    public string $pk_field = 'category.id';

    public ?array $joins = [
        'sub_category' => ['id' => 'category_id'],
    ];

    public ?array $joinTypes = [
        'sub_category' => JoinType::LEFT,
    ];

    public ?array $joinAliases = [
        'sub_category' => 'sc',
    ];

    public ?array $createColumns = [
        'name',
        'active',
    ];

    public ?array $listColumns = [
        'category.id',
        'category.name',
        'sc.name(sub_category_name)',
    ];
}
