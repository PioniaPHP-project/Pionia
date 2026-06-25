<?php

namespace Logging;

use Pionia\Logging\LogManager;
use Pionia\TestSuite\PioniaTestCase;
use Psr\Log\LoggerInterface;

class LogManagerTest extends PioniaTestCase
{
    public function testDefaultChannelReturnsLogger(): void
    {
        $manager = realm()->get(LogManager::class);

        $this->assertInstanceOf(LoggerInterface::class, $manager->channel());
    }

    public function testExtendRegistersNamedChannel(): void
    {
        $manager = realm()->get(LogManager::class);
        $manager->extend('api', ['driver' => 'single', 'path' => 'storage/logs/api.log']);

        $this->assertInstanceOf(LoggerInterface::class, $manager->channel('api'));
    }

    public function testLoggerHelperSupportsNamedChannel(): void
    {
        realm()->get(LogManager::class)->extend('jobs', ['driver' => 'single']);

        $this->assertInstanceOf(LoggerInterface::class, logger('jobs'));
    }
}
