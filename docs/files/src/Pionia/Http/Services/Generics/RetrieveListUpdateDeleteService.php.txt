<?php

namespace Pionia\Http\Services\Generics;


use Pionia\Http\Services\Generics\Mixins\DeleteMixin;
use Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Http\Services\Generics\Mixins\UpdateMixin;
use Pionia\Http\Services\GenericService;

/**
 * Adds the retrieve, update and delete actions to the service.
 */
abstract class RetrieveListUpdateDeleteService extends GenericService
{
    use RetrieveMixin, UpdateMixin, DeleteMixin, ListMixin;
}
