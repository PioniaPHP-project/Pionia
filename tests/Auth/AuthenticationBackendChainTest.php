<?php

namespace Auth;

use Pionia\Auth\AuthenticationChain;
use Pionia\Auth\ContextUserObject;
use Pionia\Collections\Arrayable;
use Pionia\Realm\AppRealm;
use Pionia\TestSuite\Mocks\AuthenticationMock;
use Pionia\TestSuite\PioniaTestCase;


class AuthenticationBackendChainTest extends PioniaTestCase
{
    private AuthenticationChain $chain;

    public function setUp(): void
    {
        parent::setUp();
        realm()->set(AppRealm::AUTHENTICATIONS_TAG, new Arrayable([]));
        $this->chain = new AuthenticationChain();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->chain);
    }

    public function testAuthenticationChainCreation()
    {
        $this->assertNotNull($this->chain);
    }

    public function testIsAuthenticationBackend()
    {
        self::assertTrue($this->chain->isAuthenticationContract(AuthenticationMock::class));
    }

    public function testAuthenticationRegistration()
    {
        $this->chain->addAuthentication(AuthenticationMock::class);

        $this->assertNotNull($this->chain->getAuthentications());
    }

    public function testAuthenticationReturnCorrectly()
    {
        $this->chain->addAuthentication(AuthenticationMock::class);

        $auth = new AuthenticationMock(realm());

        $authenticate = $auth->authenticate($this->request);

        self::assertIsObject($authenticate);
    }

    public function testRequestIsAuthenticated()
    {
        $this->chain->addAuthentication(AuthenticationMock::class);
        $this->chain->handle($this->request);
        // we check if the auth object is an instance of ContextUserObject
        self::assertInstanceOf(ContextUserObject::class, $this->request->getAuth());
        // we check if the request is authenticated
        self::assertTrue($this->request->isAuthenticated());
        // we check if permission are set correctly
        self::assertIsArray($this->request->getAuth()->permissions);
    }
}
