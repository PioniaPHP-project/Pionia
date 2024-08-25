<?php

namespace Pionia\Pionia\Contracts;

use Pionia\Pionia\Http\Request\Request;

interface KernelContract
{
    public function handle(Request $request);

//    public function terminate(Request $request, Response $response);

    public function boot(Request $request): void;

    /**
     * Get the application instance.
     *
     * @return ApplicationContract
     */
    public function getApplication(): ApplicationContract;
}
