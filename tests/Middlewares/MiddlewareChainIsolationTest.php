<?php

namespace Middlewares;

use Pionia\Collections\Arrayable;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\TestSuite\Mocks\MiddlewareMock;
use Pionia\TestSuite\PioniaTestCase;

class MiddlewareChainIsolationTest extends PioniaTestCase
{
    public function testConstructorClonesMiddlewareStackFromRealm(): void
    {
        $original = app()->getOrDefault(app()::MIDDLEWARE_TAG, new Arrayable([]));
        $beforeCount = $original instanceof Arrayable ? $original->size() : count((array) $original);

        $chain = new MiddlewareChain();
        $chain->add(MiddlewareMock::class);

        $after = app()->getOrDefault(app()::MIDDLEWARE_TAG, new Arrayable([]));
        $afterCount = $after instanceof Arrayable ? $after->size() : count((array) $after);

        $this->assertSame($beforeCount, $afterCount);
        $this->assertContains(MiddlewareMock::class, array_values($chain->all()));
    }
}
