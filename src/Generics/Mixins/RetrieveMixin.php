<?php

namespace Pionia\Generics\Mixins;

use Pionia\Response\BaseResponse;
use Porm\exceptions\BaseDatabaseException;

/**
 * This mixin adds the retrieve method to the class that uses it.
 *
 * Retrieve returns one item from the database depending on primary key field specified
 */
trait RetrieveMixin
{
    /**
     * @throws BaseDatabaseException
     */
    public function retrieve(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, null, $this->getOne());
    }
}
