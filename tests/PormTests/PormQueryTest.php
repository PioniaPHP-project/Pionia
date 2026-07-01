<?php

namespace PormTests;

use Exception;
use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;

use function table;

class PormQueryTest extends PioniaTestCase
{
    private string $connectionName = 'porm_query_test';

    protected function setUp(): void
    {
        parent::setUp();

        $connection = Connection::open([
            'type' => 'sqlite',
            'database' => ':memory:',
        ]);
        connectionManager()->register($this->connectionName, $connection);

        $pdo = connectionManager()->connection($this->connectionName)->getPdo();
        $pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            active INTEGER DEFAULT 1
        )');
        $pdo->exec("INSERT INTO users (name, active) VALUES ('Alice', 1), ('Bob', 1), ('Carol', 0)");
    }

    protected function tearDown(): void
    {
        connectionManager()->disconnect($this->connectionName);
        parent::tearDown();
    }

    public function testCrudLifecycle(): void
    {
        $row = table('users', null, $this->connectionName)->save(['name' => 'Dan'], returnRow: false);
        $this->assertSame('Dan', $row->name);
        $this->assertNotEmpty($row->id);

        table('users', null, $this->connectionName)->update(['name' => 'Daniel'], ['id' => $row->id]);
        $fetched = table('users', null, $this->connectionName)->get((int) $row->id);
        $this->assertSame('Daniel', $fetched->name);

        table('users', null, $this->connectionName)->delete(['id' => $row->id]);
        $this->assertNull(table('users', null, $this->connectionName)->get((int) $row->id));
    }

    public function testFilterCountWithWhere(): void
    {
        $count = table('users', null, $this->connectionName)->filter(['active' => 1])->count();
        $this->assertSame(2, $count);
    }

    public function testRandomReturnsRow(): void
    {
        $row = table('users', null, $this->connectionName)->random(1);
        $this->assertNotNull($row);
        $this->assertObjectHasProperty('name', $row);
    }

    public function testStartAtRequiresLimit(): void
    {
        $this->expectException(Exception::class);
        table('users', null, $this->connectionName)->filter()->startAt(1);
    }

    public function testChunkProcessesBatches(): void
    {
        $batches = 0;
        $rowsSeen = 0;

        table('users', null, $this->connectionName)->chunk(2, function (array $rows) use (&$batches, &$rowsSeen) {
            $batches++;
            $rowsSeen += count($rows);
        });

        $this->assertGreaterThanOrEqual(2, $batches);
        $this->assertSame(3, $rowsSeen);
    }

    public function testExplainReturnsPlan(): void
    {
        $plan = table('users', null, $this->connectionName)->explain(['active' => 1]);
        $this->assertIsArray($plan);
        $this->assertNotEmpty($plan);
    }

    public function testLastQueryIsPopulated(): void
    {
        $query = table('users', null, $this->connectionName);
        $query->get(1);
        $this->assertNotNull($query->lastQuery());
        $this->assertStringContainsString('users', $query->lastQuery());
    }
}
