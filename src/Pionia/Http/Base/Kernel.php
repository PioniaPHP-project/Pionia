<?php

namespace Pionia\Pionia\Http\Base;

use Pionia\Pionia\Base\Utils\Microable;
use Pionia\Pionia\Contracts\KernelContract;
use Pionia\Pionia\Http\Request\Request;

class Kernel implements KernelContract
{
    use Microable;

    public function handle(Request $request, array $routes){
    }
}
