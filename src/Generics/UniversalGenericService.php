<?php

namespace Pionia\Generics;

use Pionia\Generics\Mixins\CreateMixin;
use Pionia\Generics\Mixins\DeleteMixin;
use Pionia\Generics\Mixins\ListMixin;
use Pionia\Generics\Mixins\RandomMixin;
use Pionia\Generics\Mixins\RetrieveMixin;
use Pionia\Generics\Mixins\UpdateMixin;

/**
 * Adds the retrieve, create, update and delete actions to the service.
 *
 * It also adds the functionality to get a random records.
 *
 * @property string $table The table to be used
 * @property int $limit The limit of the data to be returned
 * @property int $offset The offset of the data to be returned
 * @property string $pk_field The primary key field
 * @property string $connection The database connection to be used
 * @property array|string $listColumns The columns to be returned in listing data
 * @property array|null $createColumns The columns to be created
 */
class UniversalGenericService extends GenericService
{
    use CreateMixin;
    use RetrieveMixin;
    use DeleteMixin;
    use UpdateMixin;
    use RandomMixin;
    use ListMixin;
}
