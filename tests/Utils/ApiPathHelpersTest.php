<?php

namespace Utils;

use Pionia\Realm\AppRealm;
use Pionia\TestSuite\PioniaTestCase;

class ApiPathHelpersTest extends PioniaTestCase
{
    public function testDefaultApiVersionMatchesRegisteredSwitch(): void
    {
        $this->assertSame('v1', defaultApiVersion());
    }

    public function testApiVersionPathIncludesVersionSegment(): void
    {
        $path = apiVersionPath('v1');

        $this->assertStringStartsWith('/api/', $path);
        $this->assertStringEndsWith('/v1/', $path);
    }

    public function testApiPingPathAppendsPingToVersionedBase(): void
    {
        $this->assertSame(rtrim(apiVersionPath('v1'), '/') . '/ping', apiPingPath('v1'));
    }

    public function testApiPingPathUsesDefaultVersionWhenOmitted(): void
    {
        $this->assertStringContainsString('/' . defaultApiVersion() . '/ping', apiPingPath());
    }

    public function testConnectionManagerIsRegisteredInContainer(): void
    {
        $this->assertTrue(app()->has(\Pionia\Porm\ConnectionManager::class));
        $this->assertSame(
            connectionManager(),
            app()->get(\Pionia\Porm\ConnectionManager::class)
        );
    }

    public function testMainSwitchRegistersExpectedServices(): void
    {
        $this->webApplication()->bootOnce();
        $serviceMap = services('Application\\Switches\\MainSwitch::processor');

        $this->assertArrayHasKey('auth', $serviceMap);
        $this->assertArrayHasKey('category', $serviceMap);
        $this->assertArrayHasKey('sampolo', $serviceMap);
    }
}
