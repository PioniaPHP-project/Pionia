<?php

namespace Pionia\Realm;

use Pionia\Exceptions\ExceptionPipeline;

trait HandlesExceptions
{
    private ?ExceptionPipeline $exceptionPipeline = null;

    public function exceptions(): ExceptionPipeline
    {
        return $this->exceptionPipeline ??= new ExceptionPipeline($this);
    }
}
