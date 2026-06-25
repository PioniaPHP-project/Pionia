<?php

namespace Pionia\TestSuite\Concerns;

use PDO;
use Pionia\Porm\Driver\Connection;

trait UsesInMemoryDatabase
{
    protected ?PDO $testPdo = null;

    protected function useInMemoryDatabase(): Connection
    {
        $this->testPdo = new PDO('sqlite::memory:');
        $this->testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return Connection::connect([
            'type' => 'sqlite',
            'database' => ':memory:',
            'pdo' => $this->testPdo,
        ]);
    }

    protected function tearDownInMemoryDatabase(): void
    {
        $this->testPdo = null;
    }
}
