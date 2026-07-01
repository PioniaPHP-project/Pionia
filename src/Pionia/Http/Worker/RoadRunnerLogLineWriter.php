<?php

namespace Pionia\Http\Worker;

/**
 * Buffers partial RoadRunner stdout/stderr chunks into formatted lines.
 */
final class RoadRunnerLogLineWriter
{
    private string $buffer = '';

    public function __construct(
        private $writeLine,
        private readonly bool $raw = false,
    ) {
    }

    public function push(string $chunk): void
    {
        if ($chunk === '') {
            return;
        }

        $this->buffer .= $chunk;

        while (($position = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $position);
            $this->buffer = substr($this->buffer, $position + 1);
            $this->emit($line);
        }
    }

    public function flush(): void
    {
        if ($this->buffer === '') {
            return;
        }

        $this->emit($this->buffer);
        $this->buffer = '';
    }

    private function emit(string $line): void
    {
        $line = rtrim($line, "\r");
        if ($line === '') {
            return;
        }

        ($this->writeLine)($this->raw ? $line : RoadRunnerLogFormatter::format($line));
    }
}
