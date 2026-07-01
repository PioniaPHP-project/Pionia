<?php

namespace Feature;

use Pionia\Http\Request\Request;
use Pionia\Http\Services\Generics\UniversalGenericService;
use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;

class GenericServiceCrudTest extends PioniaTestCase
{
    private string $connectionName = 'crud_test';

    protected function setUp(): void
    {
        parent::setUp();

        $connection = Connection::open([
            'type' => 'sqlite',
            'database' => ':memory:',
        ]);
        connectionManager()->register($this->connectionName, $connection);

        $pdo = connectionManager()->connection($this->connectionName)->getPdo();
        $pdo->exec('CREATE TABLE crud_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            score INTEGER DEFAULT 0
        )');
        $pdo->exec("INSERT INTO crud_items (title, score) VALUES ('First', 1)");
    }

    protected function tearDown(): void
    {
        connectionManager()->disconnect($this->connectionName);
        parent::tearDown();
    }

    public function testUpdateAllowsFalsyValues(): void
    {
        $service = $this->makeService(['id' => 1, 'score' => 0]);
        $updated = $this->invokeProtected($service, 'updateItem');

        $this->assertSame(0, (int) (is_array($updated) ? $updated['score'] : $updated->score));
    }

    public function testListCapsUnboundedResults(): void
    {
        $pdo = connectionManager()->connection($this->connectionName)->getPdo();
        for ($i = 2; $i <= 15; $i++) {
            $pdo->exec("INSERT INTO crud_items (title, score) VALUES ('Row $i', $i)");
        }

        $service = $this->makeService();
        $service->maxListRows = 5;
        $items = $this->invokeProtected($service, 'allItems');

        $this->assertCount(5, $items);
    }

    public function testClientFiltersWhenEnabled(): void
    {
        $pdo = connectionManager()->connection($this->connectionName)->getPdo();
        $pdo->exec("INSERT INTO crud_items (title, score) VALUES ('Second', 2)");

        $service = $this->makeService(['score' => 2]);
        $service->allowClientFilters = true;
        $items = $this->invokeProtected($service, 'allItems');

        $this->assertCount(1, $items);
        $this->assertSame(2, (int) $items[0]['score']);
    }

    private function makeService(array $payload = []): UniversalGenericService
    {
        $request = Request::create('/api/v1/', 'POST', array_merge([
            'service' => 'crud',
            'action' => 'list',
        ], $payload));

        return new class($request) extends UniversalGenericService {
            public string $table = 'crud_items';
            public ?string $connection = 'crud_test';
            public ?array $createColumns = ['title', 'score?'];
            public ?array $updateColumns = ['title', 'score'];
        };
    }

    private function invokeProtected(object $object, string $method): mixed
    {
        $ref = new \ReflectionMethod($object, $method);

        return $ref->invoke($object);
    }
}
