<?php

namespace Pionia\Contracts;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\ApiResponse;

interface RenderableException
{
    public function render(Request $request): ApiResponse;

    public function returnCode(): int;
}
