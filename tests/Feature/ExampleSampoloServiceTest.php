<?php

namespace Feature;

use Application\Services\SampoloService;
use Pionia\Http\Request\Request;
use Pionia\Porm\Driver\Connection;
use Pionia\TestSuite\PioniaTestCase;

class ExampleSampoloServiceTest extends PioniaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = Connection::open([
            'type' => 'sqlite',
            'database' => ':memory:',
        ]);
        connectionManager()->register('default', $connection);

        $pdo = connectionManager()->connection('default')->getPdo();
        $pdo->exec('CREATE TABLE company (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE sample_table (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            file TEXT,
            company INTEGER REFERENCES company(id)
        )');
        $pdo->exec("INSERT INTO company (name) VALUES ('Acme Corp'), ('Globex')");
        $pdo->exec("INSERT INTO sample_table (name, company) VALUES ('Widget A', 1), ('Widget B', 2)");
    }

    protected function tearDown(): void
    {
        connectionManager()->disconnect('default');
        parent::tearDown();
    }

    public function testSampoloServiceInstantiatesViaContainer(): void
    {
        $request = Request::create('/api/v1/', 'POST', ['service' => 'sampolo', 'action' => 'list']);
        $service = container()->make(SampoloService::class, ['request' => $request]);

        $this->assertInstanceOf(SampoloService::class, $service);
        $this->assertSame('st', $service->baseAlias);
    }

    public function testSampoloListReturnsSuccessEnvelope(): void
    {
        $response = $this->postApi('sampolo', 'list');

        $this->assertPioniaOk($response);
    }

    public function testSampoloListWithLimitPaginates(): void
    {
        $response = $this->postApi('sampolo', 'list', ['limit' => 5]);

        $this->assertPioniaOk($response);
        $payload = $response->json();
        $this->assertArrayHasKey('total_count', $payload['returnData']);
        $this->assertArrayHasKey('results', $payload['returnData']);
    }

    public function testSampoloRandomReturnsData(): void
    {
        $response = $this->postApi('sampolo', 'random');

        $this->assertPioniaOk($response);
        $this->assertNotNull($response->json()['returnData']);
    }
}
