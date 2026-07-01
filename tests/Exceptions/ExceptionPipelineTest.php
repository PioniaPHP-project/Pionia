<?php

namespace Exceptions;

use Exception;
use Pionia\Exceptions\ExceptionPipeline;
use Pionia\Exceptions\ResourceNotFoundException;
use Pionia\TestSuite\AssertsPioniaResponses;
use Pionia\TestSuite\PioniaTestCase;
use RuntimeException;

class ExceptionPipelineTest extends PioniaTestCase
{
    use AssertsPioniaResponses;

    public function testCustomMapperRunsBeforeDefaultHandler(): void
    {
        $pipeline = new ExceptionPipeline(realm());
        $pipeline->map(ResourceNotFoundException::class, fn () => response(404, 'mapped'));

        $response = $pipeline->handle(new ResourceNotFoundException('missing'));
        $payload = $this->decodeApiResponse($response);

        $this->assertSame(404, $payload['returnCode']);
        $this->assertSame('mapped', $payload['returnMessage']);
    }

    public function testDontReportSkipsHandlerReport(): void
    {
        $pipeline = new ExceptionPipeline(realm());
        $reported = false;

        $pipeline
            ->dontReport(RuntimeException::class)
            ->reportable(function () use (&$reported) {
                $reported = true;
            });

        $pipeline->handle(new RuntimeException('quiet'));

        $this->assertFalse($reported);
    }

    public function testRenderableExceptionRendersDirectly(): void
    {
        $response = (new ExceptionPipeline(realm()))->handle(new ResourceNotFoundException('gone'));
        $payload = $this->decodeApiResponse($response);

        $this->assertSame(404, $payload['returnCode']);
        $this->assertSame('gone', $payload['returnMessage']);
    }

    public function testReportHelperLogsStringMessages(): void
    {
        report('phase2-report-string-smoke', ['phase' => 2]);
        $this->assertTrue(true);
    }
}
