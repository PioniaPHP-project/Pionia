<?php

namespace Pionia\Generics\Mixins;

use Exception;
use Pionia\Response\BaseResponse;
use Porm\exceptions\BaseDatabaseException;

/**
 * This mixin adds the random functionality to the service.
 */
trait RandomMixin
{
    /**
     * @throws BaseDatabaseException
     * @throws Exception
     */
    public function random(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, null, $this->randomItem());
    }
}
