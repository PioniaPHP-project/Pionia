<?php

namespace Pionia\Http\Services\Generics;


use Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Http\Services\Generics\Mixins\RandomMixin;
use Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Http\Services\GenericService;

/**
 * Adds the retrieve and create actions to the service.
 */
abstract class RetrieveListRandomService extends GenericService
{
    use RandomMixin, ListMixin, RetrieveMixin;
}
