<?php

namespace Pionia\Http\Services\Generics\Mixins;

use Exception;
use Pionia\Http\Response\BaseResponse;
use Porm\exceptions\BaseDatabaseException;

/**
 * This mixin adds the random functionality to the service.
 */
trait RandomMixin
{
    /**
     * Get a random item or items from the table
     * @throws BaseDatabaseException
     * @throws Exception
     */
    public function randomAction(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, null, $this->randomItem());
    }
}
