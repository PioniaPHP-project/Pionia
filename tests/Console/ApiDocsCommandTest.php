<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class ApiDocsCommandTest extends PioniaTestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = dirname(__DIR__, 2) . '/build/api-docs-test-' . uniqid('', true);
        mkdir($this->outputDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (['openapi.json', 'index.md'] as $file) {
            $path = $this->outputDir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->outputDir)) {
            rmdir($this->outputDir);
        }
        parent::tearDown();
    }

    public function testApiDocsGeneratesOpenApiAndMarkdown(): void
    {
        $code = $this->artisan('api:docs', [
            '--output' => $this->outputDir,
        ]);

        $this->assertSame(0, $code);
        $this->assertFileExists($this->outputDir . '/openapi.json');
        $this->assertFileExists($this->outputDir . '/index.md');

        $openapi = json_decode(file_get_contents($this->outputDir . '/openapi.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('3.1.0', $openapi['openapi']);
        $this->assertArrayHasKey('/api/v1', $openapi['paths']);
        $this->assertStringContainsString('Documented', $this->consoleOutput());
    }

    public function testApiDocsCheckPassesWhenOutputMatches(): void
    {
        $this->artisan('api:docs', ['--output' => $this->outputDir]);

        $code = $this->artisan('api:docs', [
            '--output' => $this->outputDir,
            '--check' => true,
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('up to date', $this->consoleOutput());
    }

    public function testApiDocsCheckFailsOnDrift(): void
    {
        $this->artisan('api:docs', ['--output' => $this->outputDir]);
        file_put_contents($this->outputDir . '/index.md', "stale\n");

        $code = $this->artisan('api:docs', [
            '--output' => $this->outputDir,
            '--check' => true,
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Drift detected', $this->consoleOutput());
    }
}
