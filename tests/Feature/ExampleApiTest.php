<?php

namespace Feature;

use Pionia\TestSuite\PioniaTestCase;

class ExampleApiTest extends PioniaTestCase
{
    public function testPingEndpointReturnsPong(): void
    {
        $response = $this->getApiPing();

        $this->assertSame(200, $response->status());
        $this->assertJsonStructure(['returnCode', 'returnMessage', 'returnData'], $response);
        $this->assertPioniaOk($response);
        $this->assertSame('pong', $response->json()['returnMessage']);
    }

    public function testAuthListActionViaPostApi(): void
    {
        $response = $this->postApi('auth', 'list_auth');

        $this->assertSame(200, $response->status());
        $this->assertPioniaOk($response);
    }

    public function testUnknownServiceReturnsStructuredError(): void
    {
        $response = $this->postApi('missing_service', 'list');

        $payload = $response->json();
        $this->assertSame(200, $response->status());
        $this->assertGreaterThan(0, $payload['returnCode']);
    }
}
