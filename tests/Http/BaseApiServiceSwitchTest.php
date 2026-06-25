<?php

namespace Http;

use Application\Switches\MainSwitch;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\TestSuite\AssertsPioniaResponses;
use Pionia\TestSuite\Concerns\InteractsWithTestEnvironment;
use Pionia\TestSuite\PioniaTestCase;
use Pionia\TestSuite\Stubs\ThrowingSwitch;

class BaseApiServiceSwitchTest extends PioniaTestCase
{
    use AssertsPioniaResponses;
    use InteractsWithTestEnvironment;
    private function decode(Response $response): array
    {
        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testUnknownServiceReturnsStructuredError(): void
    {
        $request = Request::create('http://localhost/api/v1/', 'POST', [
            'service' => 'does_not_exist',
            'action' => 'list',
        ]);

        $response = MainSwitch::processor($request);
        $payload = $this->decode($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(404, $payload['returnCode']);
    }

    public function testPingReturnsPong(): void
    {
        $request = Request::create('http://localhost/api/v1/ping', 'GET');
        $response = MainSwitch::ping($request);
        $payload = $this->decode($response);

        $this->assertSame(0, $payload['returnCode']);
        $this->assertSame('pong', $payload['returnMessage']);
    }

    public function testUnexpectedThrowableIsHandledWithoutFatal(): void
    {
        $previous = $this->captureDebugEnv();

        try {
            $this->setDebugEnv(true);
            $this->ensureSwitchContextIsArrayable();
            router(realm())->switch(ThrowingSwitch::class, 'throwtest');

            $request = Request::create('http://localhost/api/throwtest/', 'POST', [
                'service' => 'throw',
                'action' => 'boom',
            ]);

            $response = ThrowingSwitch::processor($request);
            $payload = $this->decode($response);

            $this->assertSame(500, $payload['returnCode']);
            $this->assertStringContainsString('kaboom', $payload['returnMessage']);
        } finally {
            $this->restoreDebugEnv($previous);
        }
    }

    public function testGenericThrowableMessageHiddenWhenNotInDebugMode(): void
    {
        $previous = $this->captureDebugEnv();

        try {
            $this->setDebugEnv(false);
            $this->ensureSwitchContextIsArrayable();
            router(realm())->switch(ThrowingSwitch::class, 'throwtest2');

            $request = Request::create('http://localhost/api/throwtest2/', 'POST', [
                'service' => 'throw',
                'action' => 'boom',
            ]);

            $response = ThrowingSwitch::processor($request);
            $payload = $this->decode($response);

            $this->assertSame(500, $payload['returnCode']);
            $this->assertSame('An unexpected error occurred.', $payload['returnMessage']);
        } finally {
            $this->restoreDebugEnv($previous);
        }
    }
}
