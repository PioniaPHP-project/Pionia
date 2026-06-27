<?php

namespace Pionia\Console\Helper;

use Pionia\Console\Output\OutputInterface;

final class TableStyle
{
    private string $horizontal = '-';

    private string $vertical = '|';

    private string $cross = '+';

    public function setHorizontalBorderChar(string $char): static
    {
        $this->horizontal = $char;

        return $this;
    }

    public function setVerticalBorderChar(string $char): static
    {
        $this->vertical = $char;

        return $this;
    }

    public function setCrossingChar(string $char): static
    {
        $this->cross = $char;

        return $this;
    }

    public function horizontal(): string
    {
        return $this->horizontal;
    }

    public function vertical(): string
    {
        return $this->vertical;
    }

    public function cross(): string
    {
        return $this->cross;
    }
}
