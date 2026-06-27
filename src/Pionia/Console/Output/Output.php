<?php

namespace Pionia\Console\Output;

abstract class Output implements OutputInterface
{
    protected int $verbosity = self::VERBOSITY_NORMAL;

    protected OutputFormatter $formatter;

    public function __construct(?int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = true)
    {
        $this->verbosity = $verbosity ?? self::VERBOSITY_NORMAL;
        $this->formatter = new OutputFormatter($decorated);
    }

    public function writeln(string|iterable $messages, int $options = self::OUTPUT_NORMAL): void
    {
        $this->write($messages, true, $options);
    }

    public function setVerbosity(int $level): void
    {
        $this->verbosity = $level;
    }

    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    public function isQuiet(): bool
    {
        return $this->verbosity === self::VERBOSITY_QUIET;
    }

    public function isVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERBOSE;
    }

    public function isVeryVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERY_VERBOSE;
    }

    public function isDebug(): bool
    {
        return $this->verbosity >= self::VERBOSITY_DEBUG;
    }

    public function setDecorated(bool $decorated): void
    {
        $this->formatter->setDecorated($decorated);
    }

    public function isDecorated(): bool
    {
        return $this->formatter->isDecorated();
    }

    public function getFormatter(): OutputFormatter
    {
        return $this->formatter;
    }

    protected function formatAndWrite(string|iterable $messages, bool $newline, int $options): void
    {
        if ($this->isQuiet()) {
            return;
        }

        foreach (is_iterable($messages) ? $messages : [$messages] as $message) {
            if (($options & self::OUTPUT_RAW) === 0 && ($options & self::OUTPUT_PLAIN) === 0) {
                $message = $this->formatter->format($message);
            } elseif (($options & self::OUTPUT_PLAIN) !== 0) {
                $message = strip_tags($message);
            }

            $this->doWrite($message, $newline);
        }
    }

    abstract protected function doWrite(string $message, bool $newline): void;
}
