<?php

namespace Pionia\TestSuite\Mocks;

use Pionia\Auth\AuthenticationBackend;
use Pionia\Auth\ContextUserObject;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\Helpers\HelperMocksTrait;

class AuthenticationBackendMock extends AuthenticationBackend
{
    use HelperMocksTrait;

    public function authenticate(Request $request): ?ContextUserObject
    {
        return $this->createMockContextUser();
    }
}
