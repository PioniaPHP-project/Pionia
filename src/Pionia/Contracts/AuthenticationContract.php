<?php

namespace Pionia\Pionia\Contracts;

use Pionia\Pionia\Auth\ContextUserObject;
use Pionia\Pionia\Http\Request\Request;

/**
 * Authentication contract.
 *
 * All authentication backends must extend this contract.
 */
interface  AuthenticationContract
{
    /**
     * Authenticate a request and return the user object
     */
    public function authenticate(Request $request): ?ContextUserObject;
}
