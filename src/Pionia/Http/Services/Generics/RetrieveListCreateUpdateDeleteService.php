<?php

namespace Pionia\Http\Services\Generics;



use Pionia\Http\Services\Generics\Mixins\CreateMixin;
use Pionia\Http\Services\Generics\Mixins\DeleteMixin;
use Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Http\Services\Generics\Mixins\UpdateMixin;
use Pionia\Http\Services\GenericService;

/**
 * Adds the retrieve, create and update actions to the service.
 */
abstract class RetrieveListCreateUpdateDeleteService extends GenericService
{
    use RetrieveMixin, CreateMixin, DeleteMixin, UpdateMixin, ListMixin;
}

