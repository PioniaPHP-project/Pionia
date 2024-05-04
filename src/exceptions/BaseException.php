<?php

namespace jetPhp\exceptions;

use Throwable;

/**
 * This is the base exception class that all other exceptions must extend.
 */
class BaseException  extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    #[ReturnTypeWillChange]
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
