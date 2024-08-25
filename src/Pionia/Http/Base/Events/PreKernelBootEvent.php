<?php

namespace Pionia\Pionia\Http\Base\Events;

use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Events\Event;
use Pionia\Pionia\Http\Request\Request;

/**
 * Event fired before the kernel boots
 *
 * @param PioniaApplication $app
 * @param Request $request
 */
class PreKernelBootEvent extends Event {}
