<?php

namespace Pionia\Pionia\Middlewares;

use Pionia\Pionia\Contracts\MiddlewareContract;
use Pionia\Pionia\Utils\Arrayable;

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
