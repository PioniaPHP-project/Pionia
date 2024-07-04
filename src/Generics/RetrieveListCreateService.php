<?php

namespace Pionia\Generics;

use Pionia\Generics\Base\GenericService;
use Pionia\Generics\Mixins\CreateMixin;
use Pionia\Generics\Mixins\ListMixin;
use Pionia\Generics\Mixins\RetrieveMixin;

/**
 * Adds the retrieve and create actions to the service.
 *
 * @property string $table The table to be used
 * @property int $limit The limit of the data to be returned
 * @property int $offset The offset of the data to be returned
 * @property string $pk_field The primary key field
 * @property string $connection The database connection to be used
 * @property array|string $listColumns The columns to be returned in listing data
 * @property array|null $createColumns The columns to be created
 */
class RetrieveListCreateService extends GenericService
{
    use CreateMixin;
    use RetrieveMixin;
    use ListMixin;
}
