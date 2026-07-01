<?php

namespace Http;

use Pionia\Http\Request\Request;
use Pionia\Http\Routing\Router\DefaultRoutes;
use Pionia\TestSuite\PioniaTestCase;
use Pionia\Http\Response\BinaryFileResponse;
use Pionia\Http\Response\Response;

class DefaultRoutesTest extends PioniaTestCase
{
    private DefaultRoutes $routes;

    private ?string $mediaFixture = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routes = new DefaultRoutes();

        $mediaDir = alias(\DIRECTORIES::STORAGE_DIR->name) . DIRECTORY_SEPARATOR . 'media';
        if (is_file($mediaDir)) {
            unlink($mediaDir);
        }
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0775, true);
        }
        $this->mediaFixture = $mediaDir . DIRECTORY_SEPARATOR . 'phase1-test.txt';
        file_put_contents($this->mediaFixture, 'phase1-media-fixture');
    }

    protected function tearDown(): void
    {
        if ($this->mediaFixture && file_exists($this->mediaFixture)) {
            unlink($this->mediaFixture);
        }
        parent::tearDown();
    }

    public function testHomeResolverReturnsHtmlResponseWithoutExiting(): void
    {
        $request = Request::create('/');
        $response = $this->routes->homeResolver($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());
    }

    public function testStaticRouterServesExistingFile(): void
    {
        $request = Request::create('/static/favicon.ico');
        $request->attributes->set('path', 'favicon.ico');

        $response = $this->routes->staticFilesRouter($request);
        $response->prepare($request);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThan(0, strlen($response->getContent()));
        $this->assertSame(
            (int) $response->headers->get('Content-Length'),
            strlen($response->getContent()),
        );
    }

    public function testStaticBinaryResponseSendOutputsFileBytes(): void
    {
        $path = alias(\DIRECTORIES::STATIC_DIR->name) . DIRECTORY_SEPARATOR . 'favicon.ico';
        $response = new BinaryFileResponse($path);
        $response->prepare(Request::create('/static/favicon.ico'));

        ob_start();
        $response->send();
        $body = ob_get_clean();

        $this->assertSame(filesize($path), strlen($body));
    }

    public function testStaticRouterBlocksPathTraversal(): void
    {
        $request = Request::create('/static/../../../etc/passwd');
        $request->attributes->set('path', '../../../etc/passwd');

        $response = $this->routes->staticFilesRouter($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testMediaRouterServesFileFromStorageMedia(): void
    {
        $request = Request::create('/media/phase1-test.txt');
        $request->attributes->set('path', 'phase1-test.txt');

        $response = $this->routes->mediaFilesRouter($request);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMediaRouterBlocksPathTraversal(): void
    {
        $request = Request::create('/media/../cache/hack.txt');
        $request->attributes->set('path', '../cache/hack.txt');

        $response = $this->routes->mediaFilesRouter($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testSpaFallbackServesIndexHtmlForClientRoutes(): void
    {
        $public = alias(\DIRECTORIES::PUBLIC_DIR->name);
        $index = $public . DIRECTORY_SEPARATOR . 'spa-test-index.html';
        $original = is_file($public . '/index.html') ? file_get_contents($public . '/index.html') : null;

        file_put_contents($public . '/index.html', '<!DOCTYPE html><html><body>SPA</body></html>');

        try {
            $request = Request::create('/dashboard/settings');
            $request->attributes->set('path', 'dashboard/settings');

            $response = $this->routes->spaFallbackRouter($request);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('SPA', (string) $response->getContent());
        } finally {
            if ($original !== null) {
                file_put_contents($public . '/index.html', $original);
            } elseif (is_file($public . '/index.html')) {
                unlink($public . '/index.html');
            }
        }
    }

    public function testSpaFallbackBlocksApiPaths(): void
    {
        $request = Request::create('/api/v1/ping');
        $request->attributes->set('path', 'api/v1/ping');

        $response = $this->routes->spaFallbackRouter($request);

        $this->assertSame(404, $response->getStatusCode());
    }
}
