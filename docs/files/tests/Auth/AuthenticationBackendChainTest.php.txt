<?php

namespace Auth;

use Pionia\Auth\AuthenticationChain;
use Pionia\Auth\ContextUserObject;
use Pionia\TestSuite\Mocks\AuthenticationBackendMock;
use Pionia\TestSuite\PioniaTestCase;


class AuthenticationBackendChainTest extends PioniaTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->chain = new AuthenticationChain($this->application);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->chain = null;
    }

    public function testAuthenticationChainCreation()
    {
        $this->assertNotNull($this->chain);
    }

    public function testIsAuthenticationBackend()
    {
        self::assertTrue($this->chain->isAuthenticationContract(AuthenticationBackendMock::class));
    }

    public function testAuthenticationRegistration()
    {
        $this->chain->addAuthenticationBackend(AuthenticationBackendMock::class);

        $this->assertNotNull($this->chain->getAuthentications());
    }

    public function testAuthenticationReturnCorrectly()
    {
        $this->chain->addAuthenticationBackend(AuthenticationBackendMock::class);

        $auth = new AuthenticationBackendMock($this->application->context);

        $authenticate = $auth->authenticate($this->request);

        self::assertIsObject($authenticate);
    }

    public function testRequestIsAuthenticated()
    {
        $this->chain->addAuthenticationBackend(AuthenticationBackendMock::class);
        $this->chain->handle($this->request);
        // we check if the auth object is an instance of ContextUserObject
        self::assertInstanceOf(ContextUserObject::class, $this->request->getAuth());
        // we check if the request is authenticated
        self::assertTrue($this->request->isAuthenticated());
        // we check if permission are set correctly
        self::assertIsArray($this->request->getAuth()->permissions);
    }
}
