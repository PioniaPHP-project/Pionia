<?php

/**
 * Generic CRUD demo over `sample_table` with company join.
 *
 * @moonlight-service sampolo
 * @moonlight-version v1
 * @moonlight-table sample_table
 * @moonlight-auth none
 */
namespace Application\Services;

use Pionia\Http\Services\Generics\UniversalGenericService;

class SampoloService extends UniversalGenericService
{
	public string $table = 'company';

    public ?array $createColumns = ['name'];
}
