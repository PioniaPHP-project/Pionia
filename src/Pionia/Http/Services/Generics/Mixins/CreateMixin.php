<?php

namespace Pionia\Generics\Mixins;

use Exception;
use Pionia\Core\Helpers\Utilities;
use Pionia\Response\BaseResponse;

/**
 * This mixin adds the create functionality to the service.
 */
trait CreateMixin
{
    /**
     * Create a new item in the table
     * You can use `save` as an alias for this method
     * @throws Exception
     */
    public function create(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, Utilities::singularize(Utilities::capitalize($this->table)).' created successfully', $this->createItem());
    }

    /**
     * Acronym for the `create` method
     * @throws Exception
     */
    public function save(): BaseResponse
    {
        return $this->create();
    }
}
