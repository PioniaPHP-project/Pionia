<?php

namespace TestSuite;

use Pionia\TestSuite\PioniaTestCase;

class MakesHttpRequestsTest extends PioniaTestCase
{
    public function testGetApiPingReturnsPioniaEnvelope(): void
    {
        $response = $this->getApiPing();

        $this->assertSame(200, $response->status());
        $this->assertJsonStructure(['returnCode', 'returnMessage', 'returnData'], $response);
        $this->assertPioniaOk($response);
    }

    public function testPostApiInvokesSwitchProcessor(): void
    {
        $response = $this->postApi('auth', 'list_auth');

        $this->assertPioniaOk($response);
        $this->assertStringContainsString('list_auth', $response->json()['returnMessage']);
    }

    public function testGetRootReturnsWelcomeHtml(): void
    {
        $response = $this->get('/');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('text/html', (string) $response->header('Content-Type'));
        $this->assertStringContainsString(realm()->getAppName(), $response->content());
    }

    public function testDispatchRequestUsesHandleRequestNotFly(): void
    {
        ob_start();
        $response = $this->dispatchRequest(
            \Pionia\Http\Request\Request::create(apiPingPath(), 'GET')
        );
        $output = ob_get_clean();

        $this->assertSame('', $output);
        $this->assertSame(200, $response->status());
    }
}
