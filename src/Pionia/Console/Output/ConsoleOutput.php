<?php

namespace Pionia\Console\Output;

final class ConsoleOutput extends Output
{
    private mixed $stream;

    public function __construct(
        ?int $verbosity = self::VERBOSITY_NORMAL,
        ?bool $decorated = null,
        mixed $stream = null,
    ) {
        parent::__construct($verbosity, $decorated ?? self::supportsAnsi());

        $this->stream = $stream ?? fopen('php://stdout', 'w');
    }

    public function write(string|iterable $messages, bool $newline = false, int $options = self::OUTPUT_NORMAL): void
    {
        $this->formatAndWrite($messages, $newline, $options);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        if (!is_resource($this->stream)) {
            return;
        }

        @fwrite($this->stream, $message . ($newline ? PHP_EOL : ''));
    }

    public static function supportsAnsi(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return (bool) getenv('ANSICON') || getenv('ConEmuANSI') === 'ON' || getenv('TERM');
        }

        if (!defined('STDOUT')) {
            return false;
        }

        return function_exists('stream_isatty') ? @stream_isatty(STDOUT) : true;
    }
}
