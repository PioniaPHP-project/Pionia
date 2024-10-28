<?php

namespace Pionia\Generics;

use Pionia\Generics\Base\GenericService;
use Pionia\Generics\Mixins\CreateMixin;
use Pionia\Generics\Mixins\DeleteMixin;
use Pionia\Generics\Mixins\ListMixin;
use Pionia\Generics\Mixins\RetrieveMixin;
use Pionia\Generics\Mixins\UpdateMixin;

/**
 * Adds the retrieve, create and update actions to the service.
 */
abstract class RetrieveListCreateUpdateDeleteService extends GenericService
{
    use RetrieveMixin, CreateMixin, DeleteMixin, UpdateMixin, ListMixin;
}

