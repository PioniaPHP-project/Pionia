<?php

namespace Pionia\Http\Routing\Exception;

use Pionia\Exceptions\HttpException;

class MethodNotAllowedException extends HttpException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(
        private readonly array $allowedMethods = [],
        string $message = 'Method not allowed',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 405, $previous);
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
