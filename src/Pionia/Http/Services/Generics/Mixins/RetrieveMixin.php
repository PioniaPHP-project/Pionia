<?php

namespace Pionia\Http\Services\Generics\Mixins;

use Exception;
use Pionia\Http\Response\ApiResponse;

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
     *
     * @moonlight-action retrieve
     * @moonlight-summary Fetch one row by primary key
     * @throws Exception
     */
    public function retrieveAction(): ApiResponse
    {
        return ApiResponse::jsonResponse(0, null, $this->getOne());
    }

    /**
     * Alias for the `retrieve` action
     * @throws Exception
     */
    public function detailsAction(): ApiResponse
    {
        return $this->retrieveAction();
    }
}
