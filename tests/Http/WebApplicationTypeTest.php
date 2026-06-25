<?php

namespace Http;

use Pionia\TestSuite\PioniaTestCase;
use Pionia\Utils\PioniaApplicationType;

class WebApplicationTypeTest extends PioniaTestCase
{
    public function testHttpApplicationReportsRestType(): void
    {
        $this->assertSame(PioniaApplicationType::REST, $this->application->appType());
    }

    public function testConsoleApplicationReportsConsoleType(): void
    {
        $console = app()->make(app()::CONSOLE_APP_TAG);
        $this->assertSame(PioniaApplicationType::CONSOLE, $console->appType());
    }
}
