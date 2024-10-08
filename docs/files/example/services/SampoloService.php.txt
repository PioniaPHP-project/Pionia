<?php

/**
 * This service is auto-generated from pionia cli.
 * Remember to register this service in any of your available switches.
 */

namespace Application\Services;

use Pionia\Http\Services\Generics\UniversalGenericService;
use Pionia\Http\Services\JoinType;

class SampoloService extends UniversalGenericService
{
	public string $table = 'sample_table';

    public ?array $fileColumns = ['file'];

    public ?array $createColumns = ['name', 'file?', 'company'];

    public ?array $joinAliases = [
        'company' => 'c',
    ];

    public ?array $listColumns =[
            'st.id(id)',
            'st.name(name)',
            'st.file',
            'c.name(company_name)',
    ];

    public ?array $joins = [
        'company' => ['company' => 'id']
    ];

    public ?array $joinTypes = [
        'company' => JoinType::LEFT
    ];

}
