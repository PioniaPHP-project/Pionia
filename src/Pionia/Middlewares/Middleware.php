<?php

namespace Pionia\Middlewares;

use Pionia\Collections\Arrayable;
use Pionia\Contracts\MiddlewareContract;

abstract class Middleware implements MiddlewareContract
{
    /**
     * @var Arrayable $services The services that the middleware can run against otherwise, will run against all services.
     */
  private Arrayable $services;

    /**
     * @var bool If the middleware is switched off, it will not run.
     */
  private bool $switchedOff = false;

  use MiddlewareTrait;

  public function __construct()
  {
    $this->services = new Arrayable([]);
  }
}
