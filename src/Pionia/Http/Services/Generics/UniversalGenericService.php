<?php

namespace Pionia\Generics;

use Pionia\Generics\Base\GenericService;
use Pionia\Generics\Mixins\CreateMixin;
use Pionia\Generics\Mixins\DeleteMixin;
use Pionia\Generics\Mixins\ListMixin;
use Pionia\Generics\Mixins\RandomMixin;
use Pionia\Generics\Mixins\RetrieveMixin;
use Pionia\Generics\Mixins\UpdateMixin;

/**
 * Adds the retrieve/details, create, list, update, random and delete actions to the service.
 */
abstract class UniversalGenericService extends GenericService
{
    use CreateMixin, RetrieveMixin, DeleteMixin, UpdateMixin, RandomMixin, ListMixin;
}
