<?php

namespace Pionia\Generics\Mixins;

use Pionia\Core\Helpers\Utilities;
use Pionia\Response\BaseResponse;
use Porm\exceptions\BaseDatabaseException;

/**
 * This mixin adds the create functionality to the service.
 */
trait CreateMixin
{
    /**
     * @throws BaseDatabaseException
     */
    public function create(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, Utilities::singularize($this->table).' created successfully', $this->createItem());
    }
}
