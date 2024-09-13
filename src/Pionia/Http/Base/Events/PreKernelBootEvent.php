<?php

namespace Pionia\Http\Base\Events;

use Pionia\Base\PioniaApplication;
use Pionia\Events\Event;
use Pionia\Http\Request\Request;

/**
 * Event fired before the kernel boots
 *
 * @param PioniaApplication $app
 * @param Request $request
 */
class PreKernelBootEvent extends Event {}
