<?php

namespace Pionia\TestSuite;

use Pionia\Realm\AppRealm;
use Pionia\TestSuite\Stubs\RecordingPingCommand;
use Pionia\TestSuite\Stubs\RecordingProvider;
use ReflectionProperty;

class ProviderBootTest extends PioniaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RecordingProvider::reset();
        pionia()->cacheInstance()->clear();
        $this->webApplication()->setEnv('app_providers', ['recording' => RecordingProvider::class]);
    }

    public function test_provider_hooks_run_in_boot_order(): void
    {
        $web = $this->webApplication();
        $this->resetWebBoot($web);
        $web->bootOnce();

        $this->assertSame(
            [
                'middlewares',
                'authentications',
                'commands',
                'configureLogging',
                'configureCaching',
                'configureExceptions',
                'onBooted',
                'routes',
            ],
            RecordingProvider::$bootSteps,
        );
    }

    public function test_provider_registers_commands(): void
    {
        $web = $this->webApplication();
        $this->resetWebBoot($web);
        $web->bootOnce();

        $commands = app()->get(AppRealm::COMMANDS_TAG);
        $this->assertArrayHasKey('recording_ping', $commands->all());
        $this->assertSame(RecordingPingCommand::class, $commands->get('recording_ping'));
    }

    private function resetWebBoot(\Pionia\Base\WebApplication $web): void
    {
        app()->cacheInstance()->clear();
        (new ReflectionProperty($web, 'booted'))->setValue($web, false);
    }
}
