<?php

namespace Pionia\Pionia\Contracts;

use Pionia\Pionia\Http\Request\Request;

interface ServiceContract
{
    public function processAction(Request $request);
}
