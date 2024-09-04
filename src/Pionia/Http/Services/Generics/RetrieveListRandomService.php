<?php

namespace Pionia\Pionia\Http\Services\Generics;


use Pionia\Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\RandomMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Pionia\Http\Services\GenericService;

/**
 * Adds the retrieve and create actions to the service.
 */
abstract class RetrieveListRandomService extends GenericService
{
    use RandomMixin, ListMixin, RetrieveMixin;
}
