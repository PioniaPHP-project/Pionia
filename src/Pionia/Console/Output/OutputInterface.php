<?php

namespace Pionia\Console\Output;

interface OutputInterface
{
    public const VERBOSITY_QUIET = 0;

    public const VERBOSITY_NORMAL = 1;

    public const VERBOSITY_VERBOSE = 2;

    public const VERBOSITY_VERY_VERBOSE = 3;

    public const VERBOSITY_DEBUG = 4;

    public const OUTPUT_NORMAL = 1;

    public const OUTPUT_RAW = 2;

    public const OUTPUT_PLAIN = 4;

    public function write(string|iterable $messages, bool $newline = false, int $options = self::OUTPUT_NORMAL): void;

    public function writeln(string|iterable $messages, int $options = self::OUTPUT_NORMAL): void;

    public function setVerbosity(int $level): void;

    public function getVerbosity(): int;

    public function isQuiet(): bool;

    public function isVerbose(): bool;

    public function isVeryVerbose(): bool;

    public function isDebug(): bool;

    public function setDecorated(bool $decorated): void;

    public function isDecorated(): bool;

    public function getFormatter(): OutputFormatter;
}
