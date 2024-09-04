<?php

namespace Pionia\Pionia\Http\Services\Generics;


use Pionia\Pionia\Http\Services\Generics\Mixins\DeleteMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\UpdateMixin;
use Pionia\Pionia\Http\Services\GenericService;

/**
 * Adds the retrieve, update and delete actions to the service.
 */
abstract class RetrieveListUpdateDeleteService extends GenericService
{
    use RetrieveMixin, UpdateMixin, DeleteMixin, ListMixin;
}
