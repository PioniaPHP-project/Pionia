<?php

namespace Console;

use Pionia\Scaffolding\ApplicationScaffolder;
use Pionia\TestSuite\PioniaTestCase;

class NewApplicationCommandTest extends PioniaTestCase
{
    private ?string $tempDir = null;

    protected function tearDown(): void
    {
        if ($this->tempDir && is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function testNewCommandScaffoldsApplicationTree(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/pionia-new-' . uniqid('', true);

        $code = $this->artisan('new', [
            0 => 'demo-app',
            '--path' => $this->tempDir,
        ]);

        $this->assertSame(0, $code);

        $root = $this->tempDir . '/demo-app';
        $this->assertFileExists($root . '/composer.json');
        $composer = file_get_contents($root . '/composer.json');
        $this->assertStringContainsString('PostCreateProjectHandler', $composer);
        $this->assertFileExists($root . '/pionia');
        $this->assertFileExists($root . '/bootstrap/application.php');
        $this->assertFileDoesNotExist($root . '/bootstrap/routes.php');
        $this->assertFileExists($root . '/public/index.php');
        $this->assertFileExists($root . '/switches/MainSwitch.php');
        $settings = file_get_contents($root . '/environment/settings.ini');
        $this->assertStringContainsString('[app_switches]', $settings);
        $this->assertStringContainsString('MainSwitch', $settings);
        $this->assertFileExists($root . '/services/WelcomeService.php');
        $welcome = file_get_contents($root . '/services/WelcomeService.php');
        $this->assertStringContainsString('Demo App', $welcome);
        $this->assertStringContainsString('appName()', $welcome);
        $this->assertStringContainsString('@moonlight-service welcome', $welcome);
        $env = file_get_contents($root . '/environment/.env');
        $this->assertStringContainsString('APP_NAME="Demo App"', $env);
        $this->assertFileExists($root . '/database/migrations');
        $this->assertFileExists($root . '/README.md');
        $readme = file_get_contents($root . '/README.md');
        $this->assertStringContainsString('Workspace Trust', $readme);
        $this->assertStringContainsString('Workspace Trust', $this->consoleOutput());
        $this->assertStringContainsString('make:table', $this->consoleOutput());
    }

    public function testApplicationScaffolderThrowsWhenTargetExists(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/pionia-new-' . uniqid('', true);
        mkdir($this->tempDir, 0775, true);

        $scaffolder = new ApplicationScaffolder();

        $this->expectException(\RuntimeException::class);
        $scaffolder->scaffold($this->tempDir, ['{{APP_NAME}}' => 'Test']);
    }

    public function testFrontendCommandsAreListed(): void
    {
        $code = $this->artisan('list');
        $output = $this->consoleOutput();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('frontend:scaffold', $output);
        $this->assertStringContainsString('frontend:build', $output);
        $this->assertStringContainsString('new', $output);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
