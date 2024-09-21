<?php

namespace Pionia\Http\Services\Generics\Mixins;

use Exception;
use Pionia\Http\Response\BaseResponse;

/**
 * This mixin adds the retrieve method to the class that uses it.
 *
 * Retrieve returns one item from the database depending on primary key field specified
 */
trait RetrieveMixin
{
    /**
     * Retrieve a single item from the table
     * You can use `details` as an alias for this method
     * @throws Exception
     */
    public function retrieveAction(): BaseResponse
    {
        return BaseResponse::JsonResponse(0, null, $this->getOne());
    }

    /**
     * Alias for the `retrieve` action
     * @throws Exception
     */
    public function detailsAction(): BaseResponse
    {
        return $this->retrieveAction();
    }
}
