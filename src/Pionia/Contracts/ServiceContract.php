<?php

namespace Pionia\Contracts;

use Pionia\Http\Response\BaseResponse;

interface ServiceContract
{
    public function processAction(string $action, string $service): BaseResponse;
}
