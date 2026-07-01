<?php

namespace Pionia\Http\Services\Generics\Mixins;

use Exception;
use Pionia\Http\Response\ApiResponse;

/**
 * This mixin adds the list functionality to the service.
 *
 * If pagination is defined in the request, it will be detected and applied
 *
 */
trait ListMixin
{
    /**
     * List all items in the table that match the given criteria
     *
     * @moonlight-action list
     * @moonlight-summary List rows with optional pagination
     * @throws Exception
     */
    public function listAction(): ApiResponse
    {
        return ApiResponse::jsonResponse(0, null, $this->getAllWithPagination());
    }
}
