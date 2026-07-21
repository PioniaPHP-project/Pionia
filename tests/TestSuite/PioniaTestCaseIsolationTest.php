<?php

namespace TestSuite;

use Application\Switches\MainSwitch;
use PHPUnit\Framework\Attributes\Depends;
use Pionia\Collections\Arrayable;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Realm\AppRealm;
use Pionia\TestSuite\PioniaTestCase;

class PioniaTestCaseIsolationTest extends PioniaTestCase
{
    public function testTemporarySwitchMayBeRegisteredWithinATest(): string
    {
        $this->ensureSwitchContextIsArrayable();
        (new PioniaRouter(realm()))->switch(MainSwitch::class, 'isolation');

        $switches = app()->getSilently(AppRealm::SWITCHES_TAGS);
        $this->assertInstanceOf(Arrayable::class, $switches);
        $this->assertSame(MainSwitch::class, $switches->get('isolation'));

        return 'isolation';
    }

    #[Depends('testTemporarySwitchMayBeRegisteredWithinATest')]
    public function testTemporarySwitchIsRestoredBeforeTheNextTest(string $version): void
    {
        $switches = app()->getSilently(AppRealm::SWITCHES_TAGS);
        $this->assertInstanceOf(Arrayable::class, $switches);
        $this->assertFalse($switches->has($version));
    }
}
