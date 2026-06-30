<?php

namespace Pionia\Contracts;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;

interface CorsContract
{
    public function handle(Request $request): ?Response;

    public function applyToResponse(Response $response, Request $request): Response;
}
