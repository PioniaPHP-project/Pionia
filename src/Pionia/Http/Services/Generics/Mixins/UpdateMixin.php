<?php

namespace Pionia\Http\Services\Generics\Mixins;

use Exception;
use Pionia\Http\Response\BaseResponse;
use Pionia\Utils\Support;

/**
 * This mixin adds the update functionality to the service.
 */
trait UpdateMixin
{
    /**
     * Update an item in the table
     * You can use `edit` as an alias for this method
     * @throws Exception
     */
    public function updateAction(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, Support::singularize(Support::capitalize($this->table)).' updated successfully', $this->updateItem());
    }

    /**
     * Alias for the `update` action
     * @throws Exception
     */
    public function editAction(): BaseResponse
    {
        return $this->updateAction();
    }
}
