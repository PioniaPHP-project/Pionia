<?php

namespace Pionia\Http\Services\Generics\Mixins;

use Exception;
use Pionia\Http\Response\ApiResponse;
use Pionia\Utils\Support;

/**
 * This mixin adds the create functionality to the service.
 */
trait CreateMixin
{
    /**
     * Create a new item in the table
     * You can use `save` as an alias for this method
     *
     * @moonlight-action create
     * @moonlight-summary Create a new row
     * @throws Exception
     */
    public function createAction(): ApiResponse
    {
        return ApiResponse::jsonResponse(0, Support::singularize(Support::capitalize($this->table)).' created successfully', $this->createItem());
    }

    /**
     * Alias for the `create` action
     * @throws Exception
     */
    public function saveAction(): ApiResponse
    {
        return $this->createAction();
    }
}
