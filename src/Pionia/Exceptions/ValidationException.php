<?php

namespace Pionia\Exceptions;

use Throwable;

/**
 * Thrown when request field validation fails in services or forms.
 */
class ValidationException extends HttpException
{
    public function __construct(string $message = 'Validation failed', ?Throwable $previous = null)
    {
        parent::__construct($message, 422, $previous);
    }
}
