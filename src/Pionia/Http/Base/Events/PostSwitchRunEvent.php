<?php

namespace Pionia\Pionia\Http\Base\Events;

use Pionia\Pionia\Events\Event;
use Pionia\Pionia\Http\Request\Request;
use Pionia\Pionia\Utils\Arrayable;

/**
 * Event runs after the switch runs, you can access the response from this event
 *
 * @param Arrayable $response
 * @param Request $request
 **/
class PostSwitchRunEvent extends Event {}
