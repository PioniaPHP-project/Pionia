<?php

namespace Http;

use Pionia\Http\Pages\FrameworkWelcomePage;
use Pionia\Http\Request\Request;
use Pionia\Http\Routing\Router\DefaultRoutes;
use Pionia\TestSuite\Concerns\InteractsWithTestEnvironment;
use Pionia\TestSuite\PioniaTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class FrameworkWelcomePageTest extends PioniaTestCase
{
    use InteractsWithTestEnvironment;

    public function testWelcomePageRendersFrameworkBranding(): void
    {
        $request = Request::create('/');
        $html = FrameworkWelcomePage::for($request, realm())->toResponse()->getContent();

        $this->assertStringContainsString('/__pionia/favicon.ico', $html);
        $this->assertStringContainsString('/__pionia/welcome.css', $html);
        $this->assertStringContainsString(apiPingPath(), $html);
        $this->assertStringContainsString(apiVersionPath(), $html);
        $this->assertStringContainsString('"service":"auth"', $html);
        $this->assertStringContainsString('"action":"list_auth"', $html);
        $this->assertStringNotContainsString('{self::ASSET_PREFIX}', $html);
        $this->assertStringContainsString(realm()->getAppName(), $html);
        $this->assertStringContainsString('public/index.html', $html);
    }

    public function testDebugPanelShowsAllContextTabsWhenDebugEnabled(): void
    {
        $previous = $this->captureDebugEnv();

        try {
            $this->setDebugEnv(true);
            $html = FrameworkWelcomePage::for(Request::create('/'), realm())->toResponse()->getContent();

            foreach (['Environment', 'Routes', 'Commands', 'Middlewares', 'Authentications'] as $tab) {
                $this->assertStringContainsString($tab, $html);
            }
        } finally {
            $this->restoreDebugEnv($previous);
        }
    }

    public function testDebugPanelHiddenWhenNotInDebugMode(): void
    {
        $previous = $this->captureDebugEnv();

        try {
            $this->setDebugEnv(false);
            $html = FrameworkWelcomePage::for(Request::create('/'), realm())->toResponse()->getContent();

            $this->assertStringNotContainsString('id="pioniaContextTabs"', $html);
        } finally {
            $this->restoreDebugEnv($previous);
        }
    }

    public function testFrameworkAssetsRouterServesWelcomeCss(): void
    {
        $routes = new DefaultRoutes();
        $request = Request::create('/__pionia/welcome.css');
        $request->attributes->set('path', 'welcome.css');

        $response = $routes->frameworkAssetsRouter($request);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHomeResolverUsesFrameworkWelcomeWhenNoUserIndex(): void
    {
        $routes = new DefaultRoutes();
        $response = $routes->homeResolver(Request::create('/'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('/__pionia/', (string) $response->getContent());
    }
}
