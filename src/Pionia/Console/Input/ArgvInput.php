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

    /**
     * @return list<string>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * @param list<string> $tokens
     */
    public function withTokens(array $tokens): self
    {
        return new self($tokens);
    }

    protected function parse(): void
    {
        $this->parseTokens($this->tokens);
    }
}
