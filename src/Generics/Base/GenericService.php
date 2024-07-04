<?php

namespace Pionia\Generics\Base;

use Pionia\Request\BaseRestService;

class GenericService extends BaseRestService
{
    public string $table;

    public int $limit = 10;

    public int $offset = 0;

    public string $pk_field = 'id';

    public string $connection = 'db';

    public ?array  $listColumns = null;

    public ?array $createColumns = null;

    public ?array $updateColumns = null;

    use EventsContract, CrudContract;
}
