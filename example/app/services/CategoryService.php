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
        'sub_category' => JoinType::INNER,
    ];

    public ?array $joinAliases = [
        'sub_category' => 'sc',
    ];

    public ?array $listColumns = [
        'category.id(id)',
        "category.name(category_name)",
        "sc.name(sub_category_name)",
        "sc.created_at(sub_category_created_at)",
        "category.created_at(category_created_at)",
        "active"
    ];

    public ?array $createColumns = [
        'name',
        'active',
    ];
}
