<?php

namespace Pionia\Contracts;

use Pionia\Http\Response\ApiResponse;

interface ServiceContract
{
    public function processAction(string $action, string $service): ApiResponse;
}
