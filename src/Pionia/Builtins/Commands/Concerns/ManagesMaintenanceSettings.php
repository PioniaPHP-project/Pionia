<?php

namespace Pionia\Builtins\Commands\Concerns;

trait ManagesMaintenanceSettings
{
    private function maintenanceSettingsPath(): string
    {
        return app()->envPath('settings.ini');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readSettingsIni(): array
    {
        $path = $this->maintenanceSettingsPath();
        if (!is_file($path)) {
            return [];
        }

        $settings = parse_ini_file($path, true);

        return is_array($settings) ? $settings : [];
    }

    /**
     * @param array<string, mixed> $maintenance
     */
    private function writeMaintenanceSection(array $maintenance): bool
    {
        $path = $this->maintenanceSettingsPath();
        $config = $this->readSettingsIni();
        $config['maintenance'] = array_merge($config['maintenance'] ?? [], $maintenance);

        if (!writeIniFile($path, $config)) {
            return false;
        }

        clearstatcache(true, $path);

        return true;
    }

    private function maintenanceEnabledInSettings(): bool
    {
        $maintenance = $this->readSettingsIni()['maintenance'] ?? [];

        if (!is_array($maintenance)) {
            return false;
        }

        foreach (['ENABLED', 'enabled'] as $key) {
            if (array_key_exists($key, $maintenance)) {
                return filter_var($maintenance[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return false;
    }
}
