<?php

namespace Pionia\Generics\Facades\Helpers;

use Exception;
use Pionia\Core\Helpers\Utilities;
use Pionia\Response\BaseResponse;

trait DeleteMixin
{
    /**
     * @throws Exception
     */
    public function delete(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, Utilities::singularize($this->table) . ' deleted successfully', $this->deleteItem());
    }
}
