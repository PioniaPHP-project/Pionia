<?php

namespace Pionia\Console\Output;

final class NullOutput extends Output
{
    public function write(string|iterable $messages, bool $newline = false, int $options = self::OUTPUT_NORMAL): void
    {
    }

    protected function doWrite(string $message, bool $newline): void
    {
    }
}
