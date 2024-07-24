<?php

namespace Pionia\Generics;

use Pionia\Generics\Base\GenericService;
use Pionia\Generics\Mixins\ListMixin;
use Pionia\Generics\Mixins\RetrieveMixin;
use Pionia\Generics\Mixins\UpdateMixin;

/**
 * Adds the retrieve and update actions to the service.
 */
abstract class RetrieveListUpdateService extends GenericService
{
    use RetrieveMixin, ListMixin, UpdateMixin;
}
