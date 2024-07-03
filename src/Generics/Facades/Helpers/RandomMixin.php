<?php

namespace Pionia\Generics\Facades\Helpers;

use Exception;
use Pionia\Core\Helpers\Utilities;
use Pionia\Response\BaseResponse;
use Porm\exceptions\BaseDatabaseException;

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
