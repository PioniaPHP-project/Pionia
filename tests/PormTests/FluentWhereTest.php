<?php

namespace PormTests;

use InvalidArgumentException;
use Pionia\Porm\Database\Builders\WhereExpression;
use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;

use function table;

class FluentWhereTest extends PioniaTestCase
{
    private string $connectionName = 'fluent_where_test';

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
            username TEXT NOT NULL,
            email TEXT,
            age INTEGER,
            active INTEGER DEFAULT 1
        )');
        $pdo->exec("INSERT INTO users (username, email, age, active) VALUES
            ('jet', 'jet@pionia.test', 30, 1),
            ('ada', 'ada@pionia.test', 25, 1),
            ('bob', NULL, 17, 0)");
    }

    protected function tearDown(): void
    {
        connectionManager()->disconnect($this->connectionName);
        parent::tearDown();
    }

    public function testWhereExpressionEquals(): void
    {
        $this->assertSame(['username' => 'jet'], WhereExpression::compile('username', 'jet'));
    }

    public function testWhereExpressionStartsWith(): void
    {
        $this->assertSame(
            ['username[~]' => 'je%'],
            WhereExpression::compile('username', 'starts_with', 'je')
        );
    }

    public function testWhereExpressionNotEqual(): void
    {
        $this->assertSame(
            ['username[!]' => 'jet'],
            WhereExpression::compile('username', 'not_equal', 'jet')
        );
    }

    public function testWhereExpressionMissingValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WhereExpression::compile('username', 'starts_with');
    }

    public function testFluentWhereEqualsOnBuilder(): void
    {
        $row = table('users', null, $this->connectionName)
            ->filter()
            ->where('username', 'jet')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('jet', $row->username);
    }

    public function testFluentWhereStartsWith(): void
    {
        $rows = table('users', null, $this->connectionName)
            ->filter()
            ->where('username', 'starts_with', 'j')
            ->all();

        $this->assertCount(1, $rows);
        $this->assertSame('jet', $rows[0]['username'] ?? $rows[0]->username);
    }

    public function testFluentWhereIncludes(): void
    {
        $rows = table('users', null, $this->connectionName)
            ->filter()
            ->whereIncludes('email', 'ada')
            ->all();

        $this->assertCount(1, $rows);
        $this->assertSame('ada', $rows[0]['username'] ?? $rows[0]->username);
    }

    public function testFluentWhereGreaterThan(): void
    {
        $rows = table('users', null, $this->connectionName)
            ->filter()
            ->where('age', '>', 20)
            ->all();

        $this->assertCount(2, $rows);
    }

    public function testFluentWhereOrWhere(): void
    {
        $rows = table('users', null, $this->connectionName)
            ->filter()
            ->where('username', 'jet')
            ->orWhere('email', 'ada@pionia.test')
            ->all();

        $this->assertCount(2, $rows);
    }

    public function testFluentWhereNull(): void
    {
        $rows = table('users', null, $this->connectionName)
            ->filter()
            ->whereNull('email')
            ->all();

        $this->assertCount(1, $rows);
        $this->assertSame('bob', $rows[0]['username'] ?? $rows[0]->username);
    }

    public function testFluentWhereOnTableBeforeGet(): void
    {
        $row = table('users', null, $this->connectionName)
            ->where('username', 'ada')
            ->get();

        $this->assertNotNull($row);
        $this->assertSame('ada', $row->username);
    }

    public function testFluentWhereIn(): void
    {
        $rows = table('users', null, $this->connectionName)
            ->filter()
            ->whereIn('username', ['jet', 'bob'])
            ->all();

        $this->assertCount(2, $rows);
    }

    public function testLegacyArrayWhereStillWorks(): void
    {
        $count = table('users', null, $this->connectionName)
            ->filter(['active' => 1])
            ->count();

        $this->assertSame(2, $count);
    }
}
