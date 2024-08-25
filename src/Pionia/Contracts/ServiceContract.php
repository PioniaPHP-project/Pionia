<?php

namespace Pionia\Pionia\Contracts;

use Pionia\Pionia\Http\Response\BaseResponse;

interface ServiceContract
{
    public function processAction(string $action, string $service): BaseResponse;
}
