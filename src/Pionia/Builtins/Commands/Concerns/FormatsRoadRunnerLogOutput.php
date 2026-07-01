<?php

namespace Pionia\Builtins\Commands\Concerns;

use Pionia\Http\Worker\RoadRunnerLogLineWriter;

trait FormatsRoadRunnerLogOutput
{
    private ?RoadRunnerLogLineWriter $roadRunnerLogWriter = null;

    private function roadRunnerLogWriter(): RoadRunnerLogLineWriter
    {
        if ($this->roadRunnerLogWriter !== null) {
            return $this->roadRunnerLogWriter;
        }

        $raw = method_exists($this, 'option') && (bool) $this->option('raw');

        return $this->roadRunnerLogWriter = new RoadRunnerLogLineWriter(
            fn (string $line): mixed => $this->output->writeln($line),
            raw: $raw,
        );
    }

    private function writeRoadRunnerLogChunk(string $chunk): void
    {
        $this->roadRunnerLogWriter()->push($chunk);
    }

    private function flushRoadRunnerLogWriter(): void
    {
        $this->roadRunnerLogWriter()?->flush();
    }
}
