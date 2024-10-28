<?php

namespace Pionia\Generics\Mixins;

use Exception;
use Pionia\Response\BaseResponse;

/**
 * This mixin adds the list functionality to the service.
 *
 * If pagination is defined in the request, it will be detected and applied
 *
 */
trait ListMixin
{
    /**
     * @throws Exception
     */
    public function list(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, null, $this->getAllWithPagination());
    }
}
