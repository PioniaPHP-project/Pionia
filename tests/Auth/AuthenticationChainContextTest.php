<?php

namespace Auth;

use Pionia\Auth\AuthenticationChain;
use Pionia\Collections\Arrayable;
use Pionia\Realm\AppRealm;
use Pionia\TestSuite\Mocks\AuthenticationMock;
use Pionia\TestSuite\PioniaTestCase;

class AuthenticationChainContextTest extends PioniaTestCase
{
    public function testRegisteringBackendUpdatesRealmContext(): void
    {
        realm()->set(AppRealm::AUTHENTICATIONS_TAG, new Arrayable([]));
        $chain = new AuthenticationChain();

        $chain->addAuthentication(AuthenticationMock::class);

        $stored = app()->getOrDefault(AppRealm::AUTHENTICATIONS_TAG, new Arrayable([]));
        $this->assertInstanceOf(Arrayable::class, $stored);
        $this->assertContains(AuthenticationMock::class, array_values($stored->all()));
    }
}
