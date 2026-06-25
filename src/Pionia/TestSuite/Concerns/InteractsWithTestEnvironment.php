<?php

namespace Pionia\TestSuite\Concerns;

trait InteractsWithTestEnvironment
{
    /**
     * @return array{env_debug: mixed, server_debug: mixed, env_app_debug: mixed, server_app_debug: mixed}
     */
    protected function captureDebugEnv(): array
    {
        return [
            'env_debug' => $_ENV['DEBUG'] ?? null,
            'server_debug' => $_SERVER['DEBUG'] ?? null,
            'env_app_debug' => $_ENV['APP_DEBUG'] ?? null,
            'server_app_debug' => $_SERVER['APP_DEBUG'] ?? null,
        ];
    }

    protected function setDebugEnv(bool $enabled): void
    {
        $value = $enabled ? 'true' : 'false';
        $_ENV['DEBUG'] = $value;
        $_SERVER['DEBUG'] = $value;
        $_ENV['APP_DEBUG'] = $value;
        $_SERVER['APP_DEBUG'] = $value;
    }

    /**
     * @param array{env_debug: mixed, server_debug: mixed, env_app_debug: mixed, server_app_debug: mixed} $previous
     */
    protected function restoreDebugEnv(array $previous): void
    {
        foreach ([
            'env_debug' => 'DEBUG',
            'server_debug' => 'DEBUG',
            'env_app_debug' => 'APP_DEBUG',
            'server_app_debug' => 'APP_DEBUG',
        ] as $key => $envKey) {
            if ($previous[$key] === null) {
                unset($_ENV[$envKey], $_SERVER[$envKey]);
                continue;
            }

            $_ENV[$envKey] = $previous[$key];
            $_SERVER[$envKey] = $previous[$key];
        }
    }

    protected function setDocsEnv(?bool $enabled = null, ?string $token = null): void
    {
        if ($enabled === null) {
            unset($_ENV['DOCS_ENABLED'], $_SERVER['DOCS_ENABLED']);
        } else {
            $_ENV['DOCS_ENABLED'] = $enabled ? 'true' : 'false';
            $_SERVER['DOCS_ENABLED'] = $_ENV['DOCS_ENABLED'];
        }

        if ($token === null) {
            unset($_ENV['DOCS_TOKEN'], $_SERVER['DOCS_TOKEN']);
        } else {
            $_ENV['DOCS_TOKEN'] = $token;
            $_SERVER['DOCS_TOKEN'] = $token;
        }
    }

    protected function clearDocsEnv(): void
    {
        $this->setDocsEnv(null, null);
    }

    protected function setStatsEnv(?bool $enabled = null, ?string $token = null): void
    {
        if ($enabled === null) {
            unset($_ENV['STATS_ENABLED'], $_SERVER['STATS_ENABLED']);
        } else {
            $_ENV['STATS_ENABLED'] = $enabled ? 'true' : 'false';
            $_SERVER['STATS_ENABLED'] = $_ENV['STATS_ENABLED'];
        }

        if ($token === null) {
            unset($_ENV['STATS_TOKEN'], $_SERVER['STATS_TOKEN']);
        } else {
            $_ENV['STATS_TOKEN'] = $token;
            $_SERVER['STATS_TOKEN'] = $token;
        }
    }

    protected function clearStatsEnv(): void
    {
        $this->setStatsEnv(null, null);
    }

    protected function setMaintenanceEnv(?bool $enabled = null, ?string $message = null, ?string $bypassToken = null): void
    {
        if ($enabled === null) {
            unset($_ENV['MAINTENANCE_MODE'], $_SERVER['MAINTENANCE_MODE']);
        } else {
            $_ENV['MAINTENANCE_MODE'] = $enabled ? 'true' : 'false';
            $_SERVER['MAINTENANCE_MODE'] = $_ENV['MAINTENANCE_MODE'];
        }

        if ($message === null) {
            unset($_ENV['MAINTENANCE_MESSAGE'], $_SERVER['MAINTENANCE_MESSAGE']);
        } else {
            $_ENV['MAINTENANCE_MESSAGE'] = $message;
            $_SERVER['MAINTENANCE_MESSAGE'] = $message;
        }

        if ($bypassToken === null) {
            unset($_ENV['MAINTENANCE_BYPASS_TOKEN'], $_SERVER['MAINTENANCE_BYPASS_TOKEN']);
        } else {
            $_ENV['MAINTENANCE_BYPASS_TOKEN'] = $bypassToken;
            $_SERVER['MAINTENANCE_BYPASS_TOKEN'] = $bypassToken;
        }
    }

    protected function clearMaintenanceEnv(): void
    {
        $this->setMaintenanceEnv(null, null, null);
    }
}
