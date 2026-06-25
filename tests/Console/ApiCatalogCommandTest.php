<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class ApiCatalogCommandTest extends PioniaTestCase
{
    private string $outputFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputFile = dirname(__DIR__, 2) . '/build/catalog-test-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->outputFile)) {
            unlink($this->outputFile);
        }
        parent::tearDown();
    }

    public function testApiCatalogWritesJsonFile(): void
    {
        $code = $this->artisan('api:catalog', ['--output' => $this->outputFile]);

        $this->assertSame(0, $code);
        $this->assertFileExists($this->outputFile);

        $payload = json_decode(file_get_contents($this->outputFile), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('v1', $payload);
        $this->assertArrayHasKey('sampolo', $payload['v1']);
    }
}
