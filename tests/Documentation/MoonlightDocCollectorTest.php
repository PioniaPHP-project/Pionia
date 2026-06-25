<?php

namespace Documentation;

use Pionia\Documentation\MoonlightDocCollector;
use Pionia\TestSuite\PioniaTestCase;

class MoonlightDocCollectorTest extends PioniaTestCase
{
    public function testCollectsExampleAppServicesForV1(): void
    {
        $catalog = (new MoonlightDocCollector())->collect();

        $this->assertArrayHasKey('v1', $catalog->versions);
        $this->assertArrayHasKey('auth', $catalog->versions['v1']);
        $this->assertArrayHasKey('category', $catalog->versions['v1']);
        $this->assertArrayHasKey('sampolo', $catalog->versions['v1']);

        $auth = $catalog->versions['v1']['auth'];
        $this->assertSame('Application\\Services\\AuthService', $auth->className);
        $this->assertNotEmpty($auth->actions);

        $names = array_map(static fn ($a) => $a->name, $auth->actions);
        $this->assertContains('list_auth', $names);
    }

    public function testSampoloIncludesGenericCrudActions(): void
    {
        $catalog = (new MoonlightDocCollector())->collect();
        $sampolo = $catalog->versions['v1']['sampolo'];
        $names = array_map(static fn ($a) => $a->name, $sampolo->actions);

        $this->assertContains('list', $names);
        $this->assertContains('create', $names);
        $this->assertSame('sample_table', $sampolo->table);
    }
}
