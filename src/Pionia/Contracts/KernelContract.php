<?php

namespace Pionia\Pionia\Contracts;

use Pionia\Pionia\Http\Request\Request;

interface KernelContract
{
    public function handle(Request $request, array $routes);
}
