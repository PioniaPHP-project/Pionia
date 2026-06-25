<?php

namespace Runtime;

use Pionia\Runtime\RuntimeMode;
use Pionia\TestSuite\PioniaTestCase;

class RuntimeModeTest extends PioniaTestCase
{
    public function testTestingModeWhenPioniaTestingDefined(): void
    {
        $this->assertTrue(defined('PIONIA_TESTING'));
        $this->assertSame(RuntimeMode::Testing, runtimeMode());
    }

    public function testRuntimeModeEnumValues(): void
    {
        $this->assertSame('fpm', RuntimeMode::Fpm->value);
        $this->assertSame('cli', RuntimeMode::Cli->value);
        $this->assertSame('worker', RuntimeMode::Worker->value);
        $this->assertSame('testing', RuntimeMode::Testing->value);
    }
}
