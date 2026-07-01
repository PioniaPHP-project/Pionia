<?php

namespace Pionia\TestSuite\Mocks;

use Pionia\Auth\Authentication;
use Pionia\Auth\ContextUserObject;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\Helpers\HelperMocksTrait;

class AuthenticationMock extends Authentication
{
    use HelperMocksTrait;

    public function authenticate(Request $request): ?ContextUserObject
    {
        return $this->createMockContextUser();
    }
}
