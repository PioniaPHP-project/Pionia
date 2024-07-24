<?php

namespace Pionia\Generics;

use Pionia\Generics\Base\GenericService;
use Pionia\Generics\Mixins\DeleteMixin;
use Pionia\Generics\Mixins\ListMixin;
use Pionia\Generics\Mixins\RetrieveMixin;

/**
 * Adds the delete and retrieve actions to the service.
 */
abstract class RetrieveListDeleteService extends GenericService
{
    use DeleteMixin, ListMixin, RetrieveMixin;
}
