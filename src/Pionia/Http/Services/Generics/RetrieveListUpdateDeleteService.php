<?php

namespace Pionia\Generics;

use Pionia\Generics\Base\GenericService;
use Pionia\Generics\Mixins\DeleteMixin;
use Pionia\Generics\Mixins\ListMixin;
use Pionia\Generics\Mixins\RetrieveMixin;
use Pionia\Generics\Mixins\UpdateMixin;

/**
 * Adds the retrieve, update and delete actions to the service.
 */
abstract class RetrieveListUpdateDeleteService extends GenericService
{
    use RetrieveMixin, UpdateMixin, DeleteMixin, ListMixin;
}
