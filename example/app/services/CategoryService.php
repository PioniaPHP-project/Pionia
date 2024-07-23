<?php

namespace application\services;

use Pionia\Generics\Base\JoinType;
use Pionia\Generics\UniversalGenericService;

class CategoryService extends UniversalGenericService
{
    public string $table = 'category';
    public ?array $joins = [
        'sub_category' => ['id' => 'category_id'],
    ];

    public ?array $joinTypes = [
        'sub_category' => JoinType::INNER,
    ];
//
    public ?array $joinAliases = [
        'sub_category' => 'sc',
    ];

    public ?array $createColumns = [
        'name',
        'active',
    ];
}
