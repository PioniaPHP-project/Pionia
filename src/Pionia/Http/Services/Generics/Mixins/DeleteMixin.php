<?php

namespace Pionia\Generics\Mixins;

use Exception;
use Pionia\Core\Helpers\Utilities;
use Pionia\Response\BaseResponse;

/**
 * This mixin adds the delete functionality to the service.
 */
trait DeleteMixin
{
    /**
     * @throws Exception
     */
    public function delete(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, Utilities::singularize(Utilities::capitalize($this->table)) . ' deleted successfully', $this->deleteItem());
    }
}
