<?php

namespace Pionia\Http\Services\Generics\Mixins;

use Exception;
use Pionia\Http\Response\ApiResponse;
use Pionia\Porm\Exceptions\BaseDatabaseException;

/**
 * This mixin adds the random functionality to the service.
 */
trait RandomMixin
{
    /**
     * Get a random item or items from the table
     *
     * @moonlight-action random
     * @moonlight-summary Return random row(s)
     * @throws BaseDatabaseException
     * @throws Exception
     */
    public function randomAction(): ApiResponse
    {
        return ApiResponse::jsonResponse(0, null, $this->randomItem());
    }
}
