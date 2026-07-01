<?php

namespace PormTests;

use Pionia\Porm\Database\Builders\JoinLoader;
use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;

class JoinLoaderTest extends PioniaTestCase
{
    public function testEagerAttachesRelatedRows(): void
    {
        $connection = Connection::open(['type' => 'sqlite', 'database' => ':memory:']);
        connectionManager()->register('loader_test', $connection);

        $pdo = connectionManager()->connection('loader_test')->getPdo();
        $pdo->exec('CREATE TABLE company (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE sample (id INTEGER PRIMARY KEY, company INTEGER, label TEXT)');
        $pdo->exec("INSERT INTO company (id, name) VALUES (1, 'Acme')");
        $pdo->exec("INSERT INTO sample (id, company, label) VALUES (10, 1, 'A')");

        $parents = [(object) ['id' => 10, 'company' => 1, 'label' => 'A']];
        $loaded = JoinLoader::eager($parents, 'company', 'company', 'id', 'company_row', 'loader_test');

        $this->assertSame('Acme', $loaded[0]->company_row->name ?? $loaded[0]->company_row['name'] ?? null);

        connectionManager()->disconnect('loader_test');
    }
}
