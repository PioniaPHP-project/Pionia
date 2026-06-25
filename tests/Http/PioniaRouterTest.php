<?php

namespace Http;

use Pionia\Http\Routing\BaseRoutes;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\TestSuite\AssertsPioniaResponses;
use Pionia\TestSuite\PioniaTestCase;

class PioniaRouterTest extends PioniaTestCase
{
    use AssertsPioniaResponses;
    public function testConstructorInitializesRoutesCollection(): void
    {
        $router = new PioniaRouter(realm());

        $this->assertInstanceOf(BaseRoutes::class, $router->get());
    }

    public function testSwitchRegistersApiRoutes(): void
    {
        $this->ensureSwitchContextIsArrayable();
        $router = new PioniaRouter(realm());
        $router->switch(\Application\Switches\MainSwitch::class, 'v2');

        $routes = $router->get();
        $this->assertNotNull($routes->get('v2'));
        $this->assertNotNull($routes->get('v2_noslash'));
        $this->assertNotNull($routes->get('v2_overview'));
        $this->assertNotNull($routes->get('v2_overview_noslash'));
        $this->assertNotNull($routes->get('GET_v2'));
        $this->assertNotNull($routes->get('GET_v2_noslash'));
        $this->assertNotNull($routes->get('v2_ping'));
    }
}
