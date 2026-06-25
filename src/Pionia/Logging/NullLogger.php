<?php

namespace Pionia\Logging;

use Psr\Log\AbstractLogger;

/**
 * Discards all log records. Used during PHPUnit runs and for the `null` log driver.
 */
class NullLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
    }
}
