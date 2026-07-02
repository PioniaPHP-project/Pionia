<?php

namespace Scaffolding;

use Pionia\Scaffolding\FrontendFramework;
use Pionia\Scaffolding\FrontendScaffolder;
use Pionia\Scaffolding\PostCreateProjectHandler;
use Pionia\TestSuite\PioniaTestCase;
use ReflectionMethod;

class FrontendScaffoldingTest extends PioniaTestCase
{
    public function testFrameworkResolvesFromComposerStyleFlags(): void
    {
        $this->assertSame('vue-ts', FrontendFramework::resolveFromTokens(['--vue-ts']));
        $this->assertSame('vue', FrontendFramework::resolveFromTokens(['--vue']));
        $this->assertSame('react-ts', FrontendFramework::resolveFromTokens(['react-ts']));
    }

    public function testPostCreateResolvesFrameworkFromComposerArgs(): void
    {
        $this->assertSame('vue-ts', PostCreateProjectHandler::resolveFramework(['--vue-ts']));
        $this->assertNull(PostCreateProjectHandler::resolveFramework([]));
        $this->assertNull(PostCreateProjectHandler::resolveFramework(['--not-a-template']));
    }

    public function testWithFrontendRejectsUnknownTemplate(): void
    {
        $code = $this->artisan('new', [
            0 => 'bad-frontend-test',
            '--path' => sys_get_temp_dir(),
            '--with-frontend' => 'angular',
            '--no-interaction' => true,
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Invalid --with-frontend', $this->consoleOutput());
    }

    public function testWriteApiHelperCreatesLibDirectory(): void
    {
        $root = sys_get_temp_dir() . '/pionia-scaffold-' . uniqid('', true);
        mkdir($root . '/src', 0777, true);

        try {
            $scaffolder = new FrontendScaffolder($root);
            $method = new ReflectionMethod(FrontendScaffolder::class, 'writeApiHelper');
            $method->invoke($scaffolder, $root);

            $this->assertFileExists($root . '/src/lib/pionia-api.js');
            $this->assertStringContainsString('callAction', (string) file_get_contents($root . '/src/lib/pionia-api.js'));
        } finally {
            $this->removeTree($root);
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeTree($full) : unlink($full);
        }

        rmdir($path);
    }
}
