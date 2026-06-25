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
}
