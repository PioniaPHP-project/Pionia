<?php

namespace PormTests;

use PDO;
use Pionia\Porm\ConnectionManager;
use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;

use function table;

class ConnectionManagerTest extends PioniaTestCase
{
    private function registerMemoryConnection(string $name = 'memory'): Connection
    {
        $connection = Connection::open([
            'type' => 'sqlite',
            'database' => ':memory:',
        ]);
        connectionManager()->register($name, $connection);

        return $connection;
    }

    public function testNamedConnectionIsReused(): void
    {
        $this->registerMemoryConnection();
        $manager = connectionManager();

        $this->assertSame($manager->connection('memory'), $manager->connection('memory'));
    }

    public function testConnectUsesManagerForNamedConnections(): void
    {
        $this->registerMemoryConnection('pooled');

        $this->assertSame(Connection::connect('pooled'), Connection::connect('pooled'));
    }

    public function testOpenBypassesManagerPool(): void
    {
        $first = Connection::open(['type' => 'sqlite', 'testMode' => true]);
        $second = Connection::open(['type' => 'sqlite', 'testMode' => true]);

        $this->assertNotSame($first, $second);
    }

    public function testInMemoryPdoConfigBypassesManager(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $first = Connection::connect(['type' => 'sqlite', 'database' => ':memory:', 'pdo' => $pdo]);
        $second = Connection::connect(['type' => 'sqlite', 'database' => ':memory:', 'pdo' => $pdo]);

        $this->assertNotSame($first, $second);
    }

    public function testDbTableReusesPooledConnection(): void
    {
        $connection = Connection::open([
            'type' => 'sqlite',
            'database' => ':memory:',
        ]);
        connectionManager()->register('default', $connection);
        $connection->getPdo()?->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        $pdoBefore = Connection::connect('default')->getPdo();
        $porm = table('items');
        $this->assertNotNull($porm);
        $pdoAfter = Connection::connect('default')->getPdo();

        $this->assertSame($pdoBefore, $pdoAfter);
    }

    public function testDisconnectClearsPool(): void
    {
        $this->registerMemoryConnection('temp');
        $manager = connectionManager();
        $this->assertTrue($manager->has('temp'));

        $manager->disconnect('temp');
        $this->assertFalse($manager->has('temp'));
    }

    public function testDisconnectWithoutNameClearsEntirePool(): void
    {
        $this->registerMemoryConnection('a');
        $this->registerMemoryConnection('b');
        $manager = connectionManager();

        $manager->disconnect();

        $this->assertFalse($manager->has('a'));
        $this->assertFalse($manager->has('b'));
    }

    public function testConnectionManagerIsSingletonFromContainer(): void
    {
        $first = app()->get(ConnectionManager::class);
        $second = connectionManager();

        $this->assertInstanceOf(ConnectionManager::class, $first);
        $this->assertSame($first, $second);
    }
}
