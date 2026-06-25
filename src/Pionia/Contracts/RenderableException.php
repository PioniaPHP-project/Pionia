<?php

namespace Pionia\Contracts;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\BaseResponse;

interface RenderableException
{
    public function render(Request $request): BaseResponse;

    public function returnCode(): int;
}
