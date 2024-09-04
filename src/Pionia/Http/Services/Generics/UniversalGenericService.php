<?php

namespace Pionia\Pionia\Http\Services\Generics;


use Pionia\Pionia\Http\Services\Generics\Mixins\CreateMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\DeleteMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\RandomMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\UpdateMixin;
use Pionia\Pionia\Http\Services\GenericService;

/**
 * Adds the retrieve/details, create, list, update, random and delete actions to the service.
 */
abstract class UniversalGenericService extends GenericService
{
    use CreateMixin, RetrieveMixin, DeleteMixin, UpdateMixin, RandomMixin, ListMixin;
}
