<?php

namespace PormTests;

use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

class DatabaseConnectionTest extends PioniaTestCase
{
    public Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = Connection::connect([
            'type' => 'sqlite',
            'database' => BASE_PATH . '/database.sqlite3',
            'testMode' => true,
        ]);
        $this->connection->setTestMode(true);
    }

    public function testConnection()
    {
        assertInstanceOf(Connection::class, $this->connection);
    }

    public function testConnectionIsTestMode()
    {
        assertTrue($this->connection->isTestMode());
    }

}
