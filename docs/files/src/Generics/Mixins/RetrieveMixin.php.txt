<?php

namespace Pionia\Generics\Mixins;

use Exception;
use Pionia\Response\BaseResponse;

/**
 * This mixin adds the retrieve method to the class that uses it.
 *
 * Retrieve returns one item from the database depending on primary key field specified
 */
trait RetrieveMixin
{
    /**
     * @throws Exception
     */
    public function retrieve(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, null, $this->getOne());
    }

    /**
     * @throws Exception
     */
    public function details(): BaseResponse
    {
        return $this->retrieve();
    }
}
