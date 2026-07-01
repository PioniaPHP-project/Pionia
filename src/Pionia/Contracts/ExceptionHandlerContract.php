<?php

namespace Pionia\Contracts;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\ApiResponse;
use Throwable;

interface ExceptionHandlerContract
{
    public function report(Throwable $e): void;

    public function render(Throwable $e, Request $request): ApiResponse;
}
