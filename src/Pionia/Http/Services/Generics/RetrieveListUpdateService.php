<?php

namespace Pionia\Pionia\Http\Services\Generics;


use Pionia\Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\UpdateMixin;
use Pionia\Pionia\Http\Services\GenericService;

/**
 * Adds the retrieve and update actions to the service.
 */
abstract class RetrieveListUpdateService extends GenericService
{
    use RetrieveMixin, ListMixin, UpdateMixin;
}
