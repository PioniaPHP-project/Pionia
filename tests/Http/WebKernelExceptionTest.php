<?php

namespace Http;

use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;

class WebKernelExceptionTest extends PioniaTestCase
{
    public function testUnhandledRouteExceptionReturnsHtmlPage(): void
    {
        $response = $this->webApplication()->handleRequest(
            Request::create('/this-route-does-not-exist-phase1', 'GET')
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('not found', strtolower((string) $response->getContent()));
    }

    public function testUnhandledRouteExceptionReturnsJsonForApiClients(): void
    {
        $response = $this->webApplication()->handleRequest(
            Request::create('/this-route-does-not-exist-phase1', 'GET', [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ])
        );

        $this->assertSame(404, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(404, $payload['returnCode']);
        $this->assertNull($payload['returnData']);
    }
}
