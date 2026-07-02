<?php

namespace Realm;

use Application\Switches\MainSwitch;
use Pionia\Realm\AppRealm;
use Pionia\TestSuite\PioniaTestCase;

class AppRealmBootstrapTest extends PioniaTestCase
{
    public function testCreateReturnsSameInstance(): void
    {
        $first = app();
        $second = AppRealm::create(BASE_PATH . '/bootstrap');

        $this->assertSame($first, $second);
    }

    public function testAppSwitchesAreRegisteredFromSettings(): void
    {
        $switches = app()->getSilently(AppRealm::SWITCHES_TAGS);

        $this->assertNotNull($switches);
        $this->assertSame(MainSwitch::class, $switches->get('v1'));
    }
}
