<?php

use Pionia\Pionia\Http\Response\BaseResponse;

if (!function_exists('response')) {
    /**
     * Helper function to return a response
     * @param $returnCode int
     * @param string|null $returnMessage
     * @param mixed $returnData
     * @param mixed $extraData
     * @return BaseResponse
     */
    function response(int $returnCode = 0, ?string $returnMessage = null, mixed $returnData = null, mixed $extraData = null): BaseResponse
    {
        return BaseResponse::jsonResponse($returnCode, $returnMessage, $returnData, $extraData);
    }
}
