<?php

namespace Pionia\Generics;

use Pionia\Generics\Base\GenericService;
use Pionia\Generics\Mixins\ListMixin;
use Pionia\Generics\Mixins\RandomMixin;
use Pionia\Generics\Mixins\RetrieveMixin;

/**
 * Adds the retrieve and create actions to the service.
 */
abstract class RetrieveListRandomService extends GenericService
{
    use RandomMixin, ListMixin, RetrieveMixin;
}
