<?php

namespace Pionia\Auth;

use Pionia\Contracts\AuthenticationContract;
use Pionia\Http\Request\Request;
use Pionia\Utils\Containable;

/**
 * Base class for other Authentication backend to inherit.
 *
 * You have access to the application container via `$this->context`
 */
abstract class AuthenticationBackend implements AuthenticationContract
{
    use Containable;

    /**
     * limit the services this authentication backend can run on
     * @var array
     */
    public array $limitServices = [];
    /**
     * Hook to run before the authentication has been run
     */
    public function beforeRun(Request $request) {}

    /**
     * Hook to run after the authentication has been run
     */
    public function afterRun(Request $request) {}
}
