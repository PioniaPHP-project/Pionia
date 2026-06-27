<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class MaintenanceCommandsTest extends PioniaTestCase
{
    private ?string $settingsBackup = null;

    protected function tearDown(): void
    {
        $this->restoreSettingsIni();
        parent::tearDown();
    }

    public function testMaintenanceOnAndOffToggleSettingsIni(): void
    {
        $this->backupSettingsIni();

        $code = $this->artisan('maintenance:on', [
            '--message' => 'Deploying updates.',
            '--retry-after' => '120',
            '--bypass' => 'cli-secret',
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('maintenance mode', strtolower($this->consoleOutput()));

        $settings = parse_ini_file($this->settingsPath(), true);
        $this->assertSame('true', $settings['maintenance']['ENABLED'] ?? null);
        $this->assertSame('Deploying updates.', $settings['maintenance']['MESSAGE'] ?? null);
        $this->assertSame('120', $settings['maintenance']['RETRY_AFTER'] ?? null);
        $this->assertSame('cli-secret', $settings['maintenance']['BYPASS_TOKEN'] ?? null);

        $this->assertTrue(maintenanceModeEnabled());
        $this->assertSame('Deploying updates.', maintenanceMessage());

        $code = $this->artisan('maintenance:off');
        $this->assertSame(0, $code);
        $this->assertStringContainsString('live again', strtolower($this->consoleOutput()));

        $settings = parse_ini_file($this->settingsPath(), true);
        $this->assertSame('false', $settings['maintenance']['ENABLED'] ?? null);
        $this->assertFalse(maintenanceModeEnabled());
    }

    public function testMaintenanceOffIsIdempotentWhenAlreadyLive(): void
    {
        $this->backupSettingsIni();
        $this->writeMaintenanceSection(['ENABLED' => 'false']);

        $code = $this->artisan('maintenance:off');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('not in maintenance mode', strtolower($this->consoleOutput()));
    }

    public function testDownAndUpAliasesWork(): void
    {
        $this->backupSettingsIni();

        $this->assertSame(0, $this->artisan('down', ['--message' => 'Offline']));
        $this->assertTrue(maintenanceModeEnabled());

        $this->assertSame(0, $this->artisan('up'));
        $this->assertFalse(maintenanceModeEnabled());
    }

    public function testCommandsAreListed(): void
    {
        $code = $this->artisan('list');

        $this->assertSame(0, $code);
        $output = $this->consoleOutput();
        $this->assertStringContainsString('maintenance:on', $output);
        $this->assertStringContainsString('maintenance:off', $output);
    }

    private function settingsPath(): string
    {
        return app()->envPath('settings.ini');
    }

    private function backupSettingsIni(): void
    {
        $path = $this->settingsPath();
        $this->settingsBackup = is_file($path) ? file_get_contents($path) : '';
    }

    private function restoreSettingsIni(): void
    {
        if ($this->settingsBackup === null) {
            return;
        }

        file_put_contents($this->settingsPath(), $this->settingsBackup);
        clearstatcache(true, $this->settingsPath());
        $this->settingsBackup = null;
    }

    /**
     * @param array<string, mixed> $maintenance
     */
    private function writeMaintenanceSection(array $maintenance): void
    {
        $path = $this->settingsPath();
        $config = parse_ini_file($path, true) ?: [];
        $config['maintenance'] = array_merge($config['maintenance'] ?? [], $maintenance);
        writeIniFile($path, $config);
        clearstatcache(true, $path);
    }
}
