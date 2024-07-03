<?php

namespace Pionia\Generics\Facades\Helpers;

use Pionia\Core\Helpers\Utilities;
use Pionia\Response\BaseResponse;
use Porm\exceptions\BaseDatabaseException;

trait CreateMixin
{
    /**
     * @throws BaseDatabaseException
     */
    public function create(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, Utilities::singularize($this->table).' created successfully', $this->createItem());
    }
}
