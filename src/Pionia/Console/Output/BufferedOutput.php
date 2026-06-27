<?php

namespace Pionia\Console\Output;

final class BufferedOutput extends Output
{
    private string $buffer = '';

    public function write(string|iterable $messages, bool $newline = false, int $options = self::OUTPUT_NORMAL): void
    {
        $this->formatAndWrite($messages, $newline, $options);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        $this->buffer .= $message . ($newline ? PHP_EOL : '');
    }

    public function fetch(): string
    {
        $content = $this->buffer;
        $this->buffer = '';

        return $content;
    }
}
