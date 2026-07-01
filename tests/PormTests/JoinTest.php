<?php

namespace PormTests;

use Pionia\Porm\Database\Builders\JoinOn;
use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;

use function table;

class JoinTest extends PioniaTestCase
{
    private string $connectionName = 'porm_join_test';

    protected function setUp(): void
    {
        parent::setUp();

        $connection = Connection::open([
            'type' => 'sqlite',
            'database' => ':memory:',
        ]);
        connectionManager()->register($this->connectionName, $connection);

        $pdo = connectionManager()->connection($this->connectionName)->getPdo();
        $pdo->exec('CREATE TABLE company (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE sample_table (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            company INTEGER REFERENCES company(id)
        )');
        $pdo->exec("INSERT INTO company (name) VALUES ('Acme'), ('Globex')");
        $pdo->exec("INSERT INTO sample_table (name, company) VALUES ('Widget A', 1), ('Widget B', 2), ('Orphan', NULL)");
    }

    protected function tearDown(): void
    {
        connectionManager()->disconnect($this->connectionName);
        parent::tearDown();
    }

    public function testLeftJoinWithMapOnClause(): void
    {
        $rows = table('sample_table', 'st', $this->connectionName)
            ->columns(['st.name', 'c.name(company_name)'])
            ->join()
            ->leftJoin('company', JoinOn::map('company', 'id'), 'c')
            ->orderBy(['st.id' => 'ASC'])
            ->all();

        $this->assertCount(3, $rows);
        $this->assertSame('Acme', $rows[0]['company_name'] ?? $rows[0]->company_name ?? null);
        $orphanCompany = $rows[2]['company_name'] ?? $rows[2]->company_name ?? null;
        $this->assertTrue($orphanCompany === null || $orphanCompany === '');
    }

    public function testInnerJoinWithExpressionOnClause(): void
    {
        $rows = table('sample_table', null, $this->connectionName)
            ->columns(['sample_table.name', 'company.name(company_name)'])
            ->join()
            ->inner('company', JoinOn::expression('sample_table.company = company.id'))
            ->all();

        $this->assertCount(2, $rows);
    }

    public function testJoinGetFirst(): void
    {
        $row = table('sample_table', 'st', $this->connectionName)
            ->columns(['st.name', 'c.name(company_name)'])
            ->join()
            ->left('company', JoinOn::map('company', 'id'), 'c')
            ->where('st.name', 'Widget B')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Globex', $row->company_name ?? $row->{'company_name'});
    }

    public function testJoinCountAndFluentWhere(): void
    {
        $count = table('sample_table', 'st', $this->connectionName)
            ->join()
            ->left('company', JoinOn::map('company', 'id'), 'c')
            ->filter(['st.company[!]' => null])
            ->count();

        $this->assertSame(2, $count);
    }

    public function testMultipleJoins(): void
    {
        $pdo = connectionManager()->connection($this->connectionName)->getPdo();
        $pdo->exec('CREATE TABLE tag (id INTEGER PRIMARY KEY, label TEXT)');
        $pdo->exec('CREATE TABLE sample_tag (sample_id INTEGER, tag_id INTEGER)');
        $pdo->exec("INSERT INTO tag (label) VALUES ('hot')");
        $pdo->exec('INSERT INTO sample_tag VALUES (1, 1)');

        $rows = table('sample_table', 'st', $this->connectionName)
            ->columns(['st.name', 't.label'])
            ->join()
            ->inner('sample_tag', JoinOn::map('id', 'sample_id'), 'stj')
            ->inner('tag', JoinOn::map('stj.tag_id', 'id'), 't')
            ->all();

        $this->assertCount(1, $rows);
    }

    public function testJoinOnHelpers(): void
    {
        $this->assertSame(['company' => 'id'], JoinOn::map('company', 'id'));
        $this->assertSame('user_id', JoinOn::using('user_id'));
        $this->assertSame('a.id = b.id', JoinOn::columns('a.id', 'b.id'));
    }
}
