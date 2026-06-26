<?php

namespace Http;

use Pionia\Http\Pages\FrameworkWelcomePage;
use Pionia\Http\Worker\Psr7Bridge;
use Pionia\Http\Worker\RoadRunnerWorker;
use Pionia\Runtime\RuntimeMode;
use Pionia\TestSuite\PioniaTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class Psr7BridgeTest extends PioniaTestCase
{
    public function testConvertsPsr7RequestToPioniaRequest(): void
    {
        if (!class_exists(\Nyholm\Psr7\Factory\Psr17Factory::class)) {
            $this->markTestSkipped('nyholm/psr7 is not installed');
        }

        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $psr = $factory->createServerRequest('POST', 'http://localhost:8080/api/v1/?foo=bar')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Host', 'localhost:8080')
            ->withHeader('Cookie', 'session=abc123');
        $psr = $psr->withBody($factory->createStream('{"service":"auth","action":"list_auth"}'));

        $request = Psr7Bridge::toPioniaRequest($psr);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/', $request->getPathInfo());
        $this->assertSame('bar', $request->query->get('foo'));
        $this->assertStringContainsString('"service":"auth"', (string) $request->getContent());
        $this->assertSame('abc123', $request->cookies->get('session'));
    }

    public function testConvertsSymfonyResponseToPsr7(): void
    {
        if (!class_exists(\Nyholm\Psr7\Factory\Psr17Factory::class)) {
            $this->markTestSkipped('nyholm/psr7 is not installed');
        }

        $response = new Response('{"ok":true}', 200, ['Content-Type' => 'application/json']);
        $psr = Psr7Bridge::toPsr7Response($response);

        $this->assertSame(200, $psr->getStatusCode());
        $this->assertStringContainsString('application/json', $psr->getHeaderLine('Content-Type'));
        $this->assertSame('{"ok":true}', (string) $psr->getBody());
    }

    public function testConvertsBinaryFileResponseToPsr7(): void
    {
        if (!class_exists(\Nyholm\Psr7\Factory\Psr17Factory::class)) {
            $this->markTestSkipped('nyholm/psr7 is not installed');
        }

        $path = FrameworkWelcomePage::resourcesPath() . DIRECTORY_SEPARATOR . 'welcome.css';
        $this->assertFileExists($path);

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'text/css');

        $psr = Psr7Bridge::toPsr7Response($response);

        $this->assertSame(200, $psr->getStatusCode());
        $this->assertStringContainsString('text/css', $psr->getHeaderLine('Content-Type'));
        $this->assertSame(filesize($path), $psr->getBody()->getSize());
        $this->assertNotSame('', (string) $psr->getBody());
    }

    public function testRoadRunnerAvailabilityReportsMissingPackagesGracefully(): void
    {
        $available = RoadRunnerWorker::isAvailable();
        $this->assertIsBool($available);
        $this->assertTrue($available);
    }
}
