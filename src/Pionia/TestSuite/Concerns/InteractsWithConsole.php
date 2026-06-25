<?php

namespace Pionia\TestSuite\Concerns;

use Pionia\Base\Pionia;
use Pionia\Realm\AppRealm;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

trait InteractsWithConsole
{
    protected int $lastExitCode = 0;

    protected string $lastConsoleOutput = '';

    /**
     * @param array<string, mixed> $arguments
     */
    protected function artisan(string $command, array $arguments = []): int
    {
        /** @var Pionia $console */
        $console = app()->make(AppRealm::CONSOLE_APP_TAG);
        $input = new ArrayInput(array_merge(['command' => $command], $arguments));
        $output = new BufferedOutput();

        $this->lastExitCode = $console->run($input, $output);
        $this->lastConsoleOutput = $output->fetch();

        return $this->lastExitCode;
    }

    protected function assertExitCode(int $expected): void
    {
        $this->assertSame($expected, $this->lastExitCode);
    }

    protected function consoleOutput(): string
    {
        return $this->lastConsoleOutput;
    }
}
