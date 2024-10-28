<?php

namespace Pionia\Http\Services\Generics;

use Pionia\Http\Services\Generics\Mixins\CreateMixin;
use Pionia\Http\Services\Generics\Mixins\RetrieveMixin;
use Pionia\Http\Services\Generics\Mixins\UpdateMixin;
use Pionia\Http\Services\GenericService;

/**
 * Adds retrieve, create and update actions to the service.
 */
abstract class RetrieveCreateUpdateService extends GenericService
{
    use RetrieveMixin, CreateMixin, UpdateMixin;
}
