<?php

namespace Pionia\Console\Input;

final class ArgvInput extends Input
{
    /** @var list<string> */
    private array $tokens;

    /** @param list<string>|null $argv */
    public function __construct(?array $argv = null)
    {
        $this->tokens = $argv ?? array_slice($_SERVER['argv'] ?? [], 1);
    }

    protected function parse(): void
    {
        $this->parseTokens($this->tokens);
    }
}
