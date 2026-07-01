<?php

namespace Http;

use Application\Switches\MainSwitch;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;

class SwitchLoggingTest extends PioniaTestCase
{
    public function testProcessorDoesNotRequireResponseLoggingWhenDisabled(): void
    {
        $this->assertFalse(shouldLogResponses());

        $request = Request::create('http://localhost/api/v1/ping', 'GET');
        $response = MainSwitch::ping($request);

        $this->assertSame(200, $response->getStatusCode());
    }
}
