<?php

namespace Pionia\Generics;

use Pionia\Generics\Base\GenericService;
use Pionia\Generics\Mixins\CreateMixin;
use Pionia\Generics\Mixins\ListMixin;
use Pionia\Generics\Mixins\RetrieveMixin;

/**
 * Adds the retrieve and create actions to the service.
 */
abstract class RetrieveListCreateService extends GenericService
{
    use CreateMixin, ListMixin, RetrieveMixin;
}
