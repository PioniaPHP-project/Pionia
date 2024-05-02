<?php

namespace JetPhp\Core\Interceptions;

use JetPhp\Core\Helpers\ContextUserObject;
use JetPhp\Request\Request;

/**
 * Document me!
 * @property $request
 */
abstract class BaseAuthenticationBackend
{
    /**
     * @param Request $request
     * @return ContextUserObject
     */
    public abstract function authenticate(Request $request): ContextUserObject;
}
