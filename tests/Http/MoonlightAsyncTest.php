<?php

namespace Http;

use Pionia\Http\Moonlight\Moonlight;
use Pionia\Http\Moonlight\MoonlightFrameHandler;
use Pionia\Http\Moonlight\MoonlightJobDispatcher;
use Pionia\Http\Moonlight\MoonlightJobPayload;
use Pionia\Http\Moonlight\MoonlightRegistry;
use Pionia\Realtime\RealtimeGateway;
use Pionia\TestSuite\PioniaTestCase;

class MoonlightAsyncTest extends PioniaTestCase
{
    public function testMoonlightHelperDispatchesSynchronously(): void
    {
        $response = moonlight()->dispatch('auth', 'list_auth');

        $this->assertPioniaOk($response);
    }

    public function testAsyncFallsBackToSyncInTesting(): void
    {
        $response = moonlight()->async('auth', 'list_auth');

        $this->assertPioniaOk($response);
    }

    public function testFrameHandlerReturnsEnvelope(): void
    {
        $envelope = MoonlightFrameHandler::handle([
            'service' => 'auth',
            'action' => 'list_auth',
        ]);

        $this->assertSame(0, $envelope['returnCode'] ?? null);
    }

    public function testFrameHandlerRejectsMissingService(): void
    {
        $envelope = MoonlightFrameHandler::handle(['action' => 'ping']);

        $this->assertSame(400, $envelope['returnCode'] ?? null);
    }

    public function testRegistryMergesAllServices(): void
    {
        $registry = MoonlightRegistry::resolve();

        $this->assertArrayHasKey('auth', $registry);
        $this->assertArrayHasKey('sampolo', $registry);
    }

    public function testJobDispatcherWithoutQueueRunsSync(): void
    {
        $job = new MoonlightJobPayload('auth', 'list_auth');
        $response = MoonlightJobDispatcher::dispatch($job);

        $this->assertPioniaOk($response);
    }

    public function testRealtimeGatewayChannelNaming(): void
    {
        $this->assertSame('moonlight:chat', RealtimeGateway::serviceChannel('chat'));
    }

    public function testMoonlightClassInstanceViaHelper(): void
    {
        $this->assertInstanceOf(Moonlight::class, moonlight());
    }
}
