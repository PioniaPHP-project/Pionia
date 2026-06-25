<?php

namespace Pionia\Exceptions;

use Pionia\Contracts\RenderableException;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\BaseResponse;
use Throwable;

abstract class HttpException extends BaseException implements RenderableException
{
    public function __construct(
        string $message = '',
        protected int $httpCode = 500,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpCode, $previous);
    }

    public function returnCode(): int
    {
        return $this->httpCode;
    }

    public function render(Request $request): BaseResponse
    {
        return response($this->returnCode(), $this->getMessage());
    }
}
