<?php

namespace Middlewares;

use Pionia\Middlewares\MiddlewareChain;
use Pionia\TestSuite\Mocks\MiddlewareMock;
use Pionia\TestSuite\Mocks\MiddlewareMock2;
use Pionia\TestSuite\PioniaTestCase;

class MiddlewareChainTest extends PioniaTestCase
{

    public function setUp(): void
    {
        parent::setUp();
        $this->chain = new MiddlewareChain($this->application);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->chain = null;
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
        $this->assertEquals(MiddlewareMock2::class, $this->chain->middlewareStack()->at(1));
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
        $this->assertEquals(MiddlewareMock2::class, $this->chain->middlewareStack()->at(0));
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
