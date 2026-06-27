<?php

namespace Http;

use Pionia\Http\Routing\RouteDispatcher;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;
use Pionia\Http\Response\Response;

class RouteDispatcherTest extends PioniaTestCase
{
    public function testDispatchesStaticController(): void
    {
        $request = Request::create('/api/v1/ping', 'GET');
        $request->attributes->set('_controller', \Pionia\Http\Switches\BaseApiServiceSwitch::class . '::ping');

        $response = (new RouteDispatcher())->invoke($request);

        $this->assertInstanceOf(\Pionia\Http\Response\Response::class, $response);
    }

    public function testDispatchesInstanceController(): void
    {
        $request = Request::create('/', 'GET');
        $request->attributes->set('_controller', \Pionia\Http\Routing\Router\DefaultRoutes::class . '::homeResolver');

        $response = (new RouteDispatcher())->invoke($request);

        $this->assertInstanceOf(\Pionia\Http\Response\Response::class, $response);
    }
}
