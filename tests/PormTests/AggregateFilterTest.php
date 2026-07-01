<?php

namespace PormTests;

use Exception;
use Pionia\Porm\Database\Aggregation\Agg;
use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;

use function table;

class AggregateFilterTest extends PioniaTestCase
{
    private string $connectionName = 'agg_filter_test';

    protected function setUp(): void
    {
        parent::setUp();

        $connection = Connection::open([
            'type' => 'sqlite',
            'database' => ':memory:',
        ]);
        connectionManager()->register($this->connectionName, $connection);

        $pdo = connectionManager()->connection($this->connectionName)->getPdo();
        $pdo->exec('CREATE TABLE metrics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            value INTEGER NOT NULL,
            category TEXT NOT NULL
        )');
        $pdo->exec("INSERT INTO metrics (value, category) VALUES (10, 'a'), (20, 'a'), (5, 'b')");
    }

    protected function tearDown(): void
    {
        connectionManager()->disconnect($this->connectionName);
        parent::tearDown();
    }

    public function testAggregateCountRespectsWhereClause(): void
    {
        $count = table('metrics', null, $this->connectionName)
            ->filter(['category' => 'a'])
            ->count();

        $this->assertSame(2, $count);
    }

    public function testHavingDoesNotCorruptWhereArray(): void
    {
        $builder = table('metrics', null, $this->connectionName)
            ->filter()
            ->group('category')
            ->having('value', 10, '>')
            ->having('category', 'a');

        $count = table('metrics', null, $this->connectionName)
            ->filter(['category' => 'a'])
            ->count();

        $this->assertSame(2, $count);
        $this->assertInstanceOf(\Pionia\Porm\Database\Builders\Builder::class, $builder);
    }

    public function testOrderByMergesSafely(): void
    {
        $rows = table('metrics', null, $this->connectionName)
            ->filter()
            ->orderBy(['value' => 'ASC', 'category' => 'ASC'])
            ->all();

        $this->assertCount(3, $rows);
        $this->assertSame(5, (int) $rows[0]['value']);
    }

    public function testColumnsCompareAllowsValidOperators(): void
    {
        $agg = Agg::builder()->columnsCompare('value', '=', 'id');
        $this->assertInstanceOf(Agg::class, $agg);
    }

    public function testColumnsCompareRejectsInvalidOperators(): void
    {
        $this->expectException(Exception::class);
        Agg::builder()->columnsCompare('value', 'LIKE', 'id');
    }
}
