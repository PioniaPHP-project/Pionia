<?php

namespace Pionia\Http\Routing\Exception;

use Pionia\Exceptions\HttpException;

class RouteNotFoundException extends HttpException
{
    public function __construct(string $message = 'Route not found', ?\Throwable $previous = null)
    {
        parent::__construct($message, (int) env('NOT_FOUND_CODE', 404), $previous);
    }
}
