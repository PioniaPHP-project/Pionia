<?php

namespace Http;

use Pionia\Http\Pages\HttpErrorPage;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;

class HttpErrorPageTest extends PioniaTestCase
{
    public function testWantsJsonForApiPaths(): void
    {
        $request = Request::create('/api/v1/', 'GET');

        $this->assertTrue(HttpErrorPage::wantsJson($request));
    }

    public function testWantsHtmlForBrowserAcceptOnWebPaths(): void
    {
        $request = Request::create('/missing', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);

        $this->assertFalse(HttpErrorPage::wantsJson($request));
    }

    public function testRespondReturnsHtmlWithStatusCode(): void
    {
        $request = Request::create('/missing', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $response = HttpErrorPage::respond($request, 404, 'Not found');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
    }
}
