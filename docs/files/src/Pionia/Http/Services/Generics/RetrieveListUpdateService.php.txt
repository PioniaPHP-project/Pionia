<?php

namespace Pionia\Http\Services\Generics;


use Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Http\Services\Generics\Mixins\UpdateMixin;
use Pionia\Http\Services\GenericService;

/**
 * Adds the retrieve and update actions to the service.
 */
abstract class RetrieveListUpdateService extends GenericService
{
    use RetrieveMixin, ListMixin, UpdateMixin;
}
