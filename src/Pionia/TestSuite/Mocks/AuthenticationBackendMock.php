<?php

namespace Pionia\Pionia\TestSuite\Mocks;

use Pionia\Pionia\Auth\AuthenticationBackend;
use Pionia\Pionia\Auth\ContextUserObject;
use Pionia\Pionia\Http\Request\Request;
use Pionia\Pionia\TestSuite\Helpers\HelperMocksTrait;

class AuthenticationBackendMock extends AuthenticationBackend
{
    use HelperMocksTrait;

    public function authenticate(Request $request): ?ContextUserObject
    {
        return $this->createMockContextUser();
    }
}
