<?php

namespace Pionia\Http\Base\Events;

use Pionia\Base\WebApplication;
use Pionia\Events\Event;
use Pionia\Http\Request\Request;

/**
 * Event fired before the kernel boots
 *
 * @param WebApplication $app
 * @param Request $request
 */
class PreKernelBootEvent extends Event {}
