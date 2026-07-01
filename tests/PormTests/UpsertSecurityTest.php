<?php

namespace PormTests;

use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;

use function table;

class UpsertSecurityTest extends PioniaTestCase
{
    private string $connectionName = 'upsert_security_test';

    protected function setUp(): void
    {
        parent::setUp();

        $connection = Connection::open([
            'type' => 'sqlite',
            'database' => ':memory:',
            'allow_object_cast' => false,
        ]);
        connectionManager()->register($this->connectionName, $connection);

        $pdo = connectionManager()->connection($this->connectionName)->getPdo();
        $pdo->exec('CREATE TABLE items (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            score INTEGER DEFAULT 0
        )');
        $pdo->exec("INSERT INTO items (id, name, score) VALUES (1, 'A', 5)");
    }

    protected function tearDown(): void
    {
        connectionManager()->disconnect($this->connectionName);
        parent::tearDown();
    }

    public function testNativeUpsertUpdatesExistingRow(): void
    {
        $row = table('items', null, $this->connectionName)
            ->saveOrUpdate(['id' => 1, 'name' => 'Updated', 'score' => 9]);

        $this->assertSame('Updated', $row->name);
        $this->assertSame(9, (int) $row->score);
    }

    public function testIncrementUpdateUsesBoundParameter(): void
    {
        $porm = table('items', null, $this->connectionName);
        $porm->update(['score[+]' => 3], ['id' => 1]);

        [$statement, $map] = $porm->getDatabase()->lastPrepared();
        $this->assertNotNull($statement);
        $this->assertMatchesRegularExpression('/"score" = "score" \+ :MeD/', $statement);
        $boundValues = array_map(static fn (array $entry): mixed => $entry[0], $map);
        $this->assertContains(3, $boundValues);

        $row = $porm->get(1);
        $this->assertSame(8, (int) $row->score);
    }

    public function testObjectCastDisabledByDefault(): void
    {
        $pdo = connectionManager()->connection($this->connectionName)->getPdo();
        $pdo->exec("INSERT INTO items (id, name, score) VALUES (2, 'blob', 0)");

        $pdo->exec('CREATE TABLE blobs (id INTEGER PRIMARY KEY, payload TEXT)');
        $serialized = serialize(['secret' => 'value']);
        $pdo->prepare('INSERT INTO blobs (id, payload) VALUES (1, ?)')->execute([$serialized]);

        $row = table('blobs', null, $this->connectionName)
            ->columns(['payload[Object]'])
            ->get(1);

        $this->assertIsString($row->payload);
    }
}
