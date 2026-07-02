<?php

namespace Pionia\TestSuite\Concerns;

use Pionia\Base\Pionia;
use Pionia\Realm\AppRealm;
use Pionia\Utils\PioniaApplicationType;
use Pionia\Console\Input\ArrayInput;
use Pionia\Console\Output\BufferedOutput;

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
        $this->bootConsoleForTesting($console);

        $input = new ArrayInput(array_merge(['command' => $command], $arguments));
        $input->setInteractive(false);
        $output = new BufferedOutput();

        $console->setAutoExit(false);
        $this->lastExitCode = $console->run($input, $output);
        $this->lastConsoleOutput = $output->fetch();

        return $this->lastExitCode;
    }

    private function bootConsoleForTesting(Pionia $console): void
    {
        if (!$console->isBooted()) {
            $console->powerUp(PioniaApplicationType::CONSOLE);
            $console->prepareConsole();
        }
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
