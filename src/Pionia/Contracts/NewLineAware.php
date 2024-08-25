<?php

namespace Pionia\Pionia\Contracts;

interface NewLineAware
{
    /**
     * How many trailing newlines were written.
     */
    public function newLinesWritten(): int;
}
