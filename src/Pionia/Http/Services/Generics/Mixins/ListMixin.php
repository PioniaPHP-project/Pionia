<?php

namespace Pionia\Http\Services\Generics\Mixins;

use Exception;
use Pionia\Http\Response\BaseResponse;

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
     * @throws Exception
     */
    public function listAction(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, null, $this->getAllWithPagination());
    }
}
