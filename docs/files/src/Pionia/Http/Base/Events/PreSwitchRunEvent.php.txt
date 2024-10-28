<?php

namespace Pionia\Http\Base\Events;

use Pionia\Base\PioniaApplication;
use Pionia\Events\Event;
use Pionia\Http\Request\Request;

/**
 * Event runs before the switch runs, this is the last event before the kernel forwards the request to the switch
 *
 * @param Request $request
 * @param PioniaApplication $app
 * @package Pionia\Http\Base\Events
 */
class PreSwitchRunEvent extends Event {}
