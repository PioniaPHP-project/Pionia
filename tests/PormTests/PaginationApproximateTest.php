<?php

namespace PormTests;

use Pionia\Porm\Driver\Connection;
use Pionia\Porm\PaginationCore;
use Pionia\TestSuite\PioniaTestCase;

class PaginationApproximateTest extends PioniaTestCase
{
    public function testPaginateApproximateReturnsApproximateFlag(): void
    {
        $connection = Connection::open(['type' => 'sqlite', 'database' => ':memory:']);
        connectionManager()->register('pag_approx', $connection);

        $pdo = connectionManager()->connection('pag_approx')->getPdo();
        $pdo->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY, title TEXT)');
        for ($i = 1; $i <= 12; $i++) {
            $pdo->exec("INSERT INTO pages (title) VALUES ('Page $i')");
        }

        $core = new PaginationCore(['limit' => 5, 'offset' => 0], 'pages', 5, 0, 'pag_approx');
        $page = $core
            ->init(fn ($q) => $q->filter()->orderBy(['id' => 'ASC']))
            ->paginateApproximate(countCacheTtl: 30);

        $this->assertTrue($page['approximate_count']);
        $this->assertSame(12, $page['total_count']);
        $this->assertCount(5, $page['results']);

        connectionManager()->disconnect('pag_approx');
    }
}
