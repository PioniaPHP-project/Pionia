<?php

namespace Pionia\Generics\Facades\Helpers;

use Exception;
use Pionia\Core\Helpers\Utilities;
use Pionia\Response\BaseResponse;

trait UpdateMixin
{
    /**
     * @throws Exception
     */
    public function update(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, Utilities::singularize($this->table).' updated successfully', $this->updateItem());
    }
}
