<?php

namespace Pionia\Exceptions;

/**
 * This exception is thrown when a user is not authorized to access a resource
 */
class UserUnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Forbidden', ?\Throwable $previous = null)
    {
        parent::__construct($message, (int) env('unauthorized_code', 403), $previous);
    }
}
