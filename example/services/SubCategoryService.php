<?php

/**
 * This service is auto-generated from pionia cli.
 * Remember to register this service in any of your available switches.
 */

namespace Application\Services;

use Pionia\Http\Services\Generics\UniversalGenericService;

class SubCategoryService extends UniversalGenericService
{
	public string $table = 'sub_category';

    public ?array $createColumns = ['name'];

    public ?array $listColumns = ['id', 'name'];

    public ?array $joinAliases = ['category' => 'category_id'];
}
