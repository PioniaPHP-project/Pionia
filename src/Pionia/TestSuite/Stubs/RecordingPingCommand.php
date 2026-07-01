<?php

namespace Pionia\TestSuite\Stubs;

use Pionia\Console\Command;

final class RecordingPingCommand extends Command
{
    protected string $name = 'recording:ping';

    protected string $description = 'Test command registered by RecordingProvider.';

    protected function handle(): int
    {
        return Command::SUCCESS;
    }
}
