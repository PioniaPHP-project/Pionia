<?php

namespace Pionia\Process;

final class ProcessSignaledException extends \RuntimeException
{
    public function __construct(
        private readonly int $signal,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf('The process has been signaled with signal "%d".', $signal));
    }

    public function getSignal(): int
    {
        return $this->signal;
    }
}
