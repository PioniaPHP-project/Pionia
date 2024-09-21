<?php

namespace Pionia\Http\Base\Events;

use Pionia\Collections\Arrayable;
use Pionia\Events\Event;
use Pionia\Http\Request\Request;

/**
 * Event runs after the switch runs, you can access the response from this event
 *
 * @param Arrayable $response
 * @param Request $request
 **/
class PostSwitchRunEvent extends Event {}
