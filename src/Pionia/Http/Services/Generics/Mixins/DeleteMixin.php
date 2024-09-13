<?php

namespace Pionia\Http\Services\Generics\Mixins;

use Exception;
use Pionia\Http\Response\BaseResponse;
use Pionia\Utils\Support;

/**
 * This mixin adds the delete functionality to the service.
 */
trait DeleteMixin
{
    /**
     * Delete an item in the table
     * @throws Exception
     */
    public function deleteAction(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, Support::singularize(Support::capitalize($this->table)) . ' deleted successfully', $this->deleteItem());
    }
}
