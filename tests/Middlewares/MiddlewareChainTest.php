<?php

namespace Middlewares;

use Pionia\Collections\Arrayable;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Realm\AppRealm;
use Pionia\TestSuite\Mocks\MiddlewareMock;
use Pionia\TestSuite\Mocks\MiddlewareMock2;
use Pionia\TestSuite\PioniaTestCase;

class MiddlewareChainTest extends PioniaTestCase
{
    private MiddlewareChain $chain;

    public function setUp(): void
    {
        parent::setUp();
        realm()->set(AppRealm::MIDDLEWARE_TAG, new Arrayable([]));
        $this->chain = new MiddlewareChain();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->chain);
    }

    public function testMiddlewareChainCreation()
    {
        $this->assertNotNull($this->chain);
    }

    public function testAddMiddlewareToChain()
    {
        $this->chain->add(MiddlewareMock::class);
        $this->assertNotNull($this->chain->all());
    }

    /**
     * Tests that a given class string is a valid middleware
     */
    public function testIsMiddleware()
    {
        $this->assertTrue($this->chain->isAMiddleware(MiddlewareMock::class));
    }

    /**
     * Tests that the middleware is added after the specified middleware
     * @return void
     */
    public function testAddAfter()
    {
        $this->chain->add(MiddlewareMock::class);
        $this->chain->addAfter(MiddlewareMock::class, MiddlewareMock2::class);
        $stack = array_values($this->chain->all());
        $mockIndex = array_search(MiddlewareMock::class, $stack, true);
        $mock2Index = array_search(MiddlewareMock2::class, $stack, true);
        $this->assertNotFalse($mockIndex);
        $this->assertNotFalse($mock2Index);
        $this->assertSame($mockIndex + 1, $mock2Index);
    }

    /**
     * Tests that the middleware is added before the specified middleware
     * @return void
     * @throws \Exception
     */
    public function testAddBefore()
    {
        $this->chain->add(MiddlewareMock::class);
        $this->chain->addBefore(MiddlewareMock::class, MiddlewareMock2::class);
        $stack = array_values($this->chain->all());
        $mockIndex = array_search(MiddlewareMock::class, $stack, true);
        $mock2Index = array_search(MiddlewareMock2::class, $stack, true);
        $this->assertNotFalse($mockIndex);
        $this->assertNotFalse($mock2Index);
        $this->assertLessThan($mockIndex, $mock2Index);
    }

    /**
     * Tests that the middleware chain is executed
     * @return void
     */
    public function testExecuteMiddlewareChain()
    {
        $this->chain->add(MiddlewareMock::class);
        $this->chain->add(MiddlewareMock2::class);
        $this->chain->handle($this->request);
        $this->assertEquals('test-header', $this->request->headers->get('X-Test-Header'));
    }
}
