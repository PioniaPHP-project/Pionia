<?php

namespace Pionia\Http\Services\Generics;


use Pionia\Http\Services\Generics\Mixins\DeleteMixin;
use Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Http\Services\GenericService;

/**
 * Adds the delete and retrieve actions to the service.
 */
abstract class RetrieveListDeleteService extends GenericService
{
    use DeleteMixin, ListMixin, RetrieveMixin;
}
