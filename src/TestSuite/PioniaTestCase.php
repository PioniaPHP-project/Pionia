<?php

namespace Pionia\TestSuite;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Pionia\Pionia\Base\PioniaApplication;
use Psr\Container\ContainerInterface;

class PioniaTestCase extends TestCase
{
    public ?PioniaApplication $application = null;
    public ContainerInterface | Container | null $context = null;

    protected function setUp(): void
    {
        $this->context = new Container();

        $this->application = new PioniaApplication($this->context);
    }


    protected function tearDown(): void
    {
        $this->application = null;
        $this->context = null;
    }
}
