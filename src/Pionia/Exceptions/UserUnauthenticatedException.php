<?php

namespace Pionia\Exceptions;

/**
 * This exception is thrown when one tries to access a protected resource without being authenticated
 */
class UserUnauthenticatedException extends HttpException
{
    public function __construct(string $message = 'Unauthenticated', ?\Throwable $previous = null)
    {
        parent::__construct($message, (int) env('unauthenticated_code', 401), $previous);
    }
}
