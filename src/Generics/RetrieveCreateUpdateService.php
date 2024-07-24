<?php

namespace Pionia\Generics;

use Pionia\Generics\Base\GenericService;
use Pionia\Generics\Mixins\CreateMixin;
use Pionia\Generics\Mixins\RetrieveMixin;
use Pionia\Generics\Mixins\UpdateMixin;

/**
 * Adds retrieve, create and update actions to the service.
 */
abstract class RetrieveCreateUpdateService extends GenericService
{
    use RetrieveMixin, CreateMixin, UpdateMixin;
}
