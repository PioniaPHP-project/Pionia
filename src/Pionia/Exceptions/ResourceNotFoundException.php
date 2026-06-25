<?php

namespace Pionia\Exceptions;

/**
 * This exception is thrown when a service requested is not found.
 */
class ResourceNotFoundException extends HttpException
{
    public function __construct(string $message = 'Not found', ?\Throwable $previous = null)
    {
        parent::__construct($message, (int) env('not_found_code', 404), $previous);
    }
}
