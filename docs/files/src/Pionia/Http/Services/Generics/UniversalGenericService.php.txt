<?php

namespace Pionia\Http\Services\Generics;


use Pionia\Http\Services\Generics\Mixins\CreateMixin;
use Pionia\Http\Services\Generics\Mixins\DeleteMixin;
use Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Http\Services\Generics\Mixins\RandomMixin;
use Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Http\Services\Generics\Mixins\UpdateMixin;
use Pionia\Http\Services\GenericService;

/**
 * Adds the retrieve/details, create, list, update, random and delete actions to the service.
 */
abstract class UniversalGenericService extends GenericService
{
    use CreateMixin, RetrieveMixin, DeleteMixin, UpdateMixin, RandomMixin, ListMixin;
}
