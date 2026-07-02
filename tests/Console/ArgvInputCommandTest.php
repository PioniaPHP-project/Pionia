<?php

namespace Console;

use Pionia\Console\Input\ArgvInput;
use Pionia\Console\Output\BufferedOutput;
use Pionia\Realm\AppRealm;
use Pionia\TestSuite\PioniaTestCase;

class ArgvInputCommandTest extends PioniaTestCase
{
    public function testArgvInputStripsCommandNameForOptionOnlyCommands(): void
    {
        /** @var \Pionia\Base\Pionia $console */
        $console = app()->make(AppRealm::CONSOLE_APP_TAG);

        if (!$console->isBooted()) {
            $console->powerUp(\Pionia\Utils\PioniaApplicationType::CONSOLE);
            $console->prepareConsole();
        }

        $input = new ArgvInput(['stopserver']);
        $output = new BufferedOutput();

        $console->setAutoExit(false);
        $code = $console->run($input, $output);

        $this->assertContains($code, [0, 1]);
        $this->assertStringNotContainsString('Too many arguments', $output->fetch());
    }

    public function testArgvInputStripsCommandNameForRequiredArgumentCommands(): void
    {
        /** @var \Pionia\Base\Pionia $console */
        $console = app()->make(AppRealm::CONSOLE_APP_TAG);

        if (!$console->isBooted()) {
            $console->powerUp(\Pionia\Utils\PioniaApplicationType::CONSOLE);
            $console->prepareConsole();
        }

        $tempDir = BASE_PATH . '/storage/cache/argv-test-' . uniqid('', true);
        mkdir($tempDir, 0775, true);

        try {
            $input = new ArgvInput(['new', 'argv-demo', '--path=' . $tempDir]);
            $output = new BufferedOutput();

            $console->setAutoExit(false);
            $code = $console->run($input, $output);

            $this->assertSame(0, $code, $output->fetch());
            $this->assertFileExists($tempDir . '/argv-demo/composer.json');
        } finally {
            $this->removeDirectory($tempDir . '/argv-demo');
            $this->removeDirectory($tempDir);
        }
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
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
