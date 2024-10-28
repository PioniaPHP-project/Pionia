<?php

namespace Pionia\Generics\Mixins;

use Exception;
use Pionia\Core\Helpers\Utilities;
use Pionia\Response\BaseResponse;

/**
 * This mixin adds the update functionality to the service.
 */
trait UpdateMixin
{
    /**
     * @throws Exception
     */
    public function update(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, Utilities::singularize(Utilities::capitalize($this->table)).' updated successfully', $this->updateItem());
    }
}
