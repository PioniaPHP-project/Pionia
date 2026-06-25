<?php

namespace PormTests;

use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;

class DatabaseConnectionTest extends PioniaTestCase
{
  public function testInMemorySqliteConnection(): void
    {
        $connection = $this->useInMemoryDatabase();

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertNotNull($this->testPdo);
    }

    public function testTestModeSkipsPdo(): void
    {
        $connection = Connection::connect([
            'type' => 'sqlite',
            'testMode' => true,
        ]);

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertTrue($connection->isTestMode());
    }
}
