<?php

namespace Realm;

use Exception;
use Pionia\Http\Request\Request;
use Pionia\Realm\GlobalExceptionHandler;
use Pionia\TestSuite\AssertsPioniaResponses;
use Pionia\TestSuite\PioniaTestCase;

class PioniaHandleExceptionTest extends PioniaTestCase
{
    use AssertsPioniaResponses;
    public function testDelegatesToConfiguredHandler(): void
    {
        realm()->setErrorHandler(GlobalExceptionHandler::class);

        $response = pionia_handle_exception(new Exception('handled'), Request::create('/api/v1/'));

        $payload = $this->decodeBaseResponse($response);

        $this->assertSame(500, $payload['returnCode']);
        $this->assertNotEmpty($payload['returnMessage']);
    }

    public function testUsesProvidedRequest(): void
    {
        realm()->setErrorHandler(GlobalExceptionHandler::class);
        $request = Request::create('http://localhost/custom', 'POST');

        $response = pionia_handle_exception(new Exception('ctx'), $request);

        $this->assertInstanceOf(\Pionia\Http\Response\BaseResponse::class, $response);
    }
}
