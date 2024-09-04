<?php

namespace Pionia\Pionia\Http\Services\Generics;

use Pionia\Pionia\Http\Services\Generics\Mixins\CreateMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\ListMixin;
use Pionia\Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Pionia\Http\Services\GenericService;

/**
 * Adds the retrieve and create actions to the service.
 */
abstract class RetrieveListCreateService extends GenericService
{
    use CreateMixin, ListMixin, RetrieveMixin;
}
