<?php

namespace Pionia\Utils;

trait AppHelpersTrait
{
    private string $APP_NAME = 'Pionia';

    private string $version = '2.0.0';

    /**
     * Our application name
     * @return string
     */
    public function appName(): string
    {
        return $this->getSilently("APP_NAME") ?? $this->APP_NAME;
    }

    /**
     * @return string The version of the application
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * Change the core app name
     * @param $name
     * @return void
     */
    public function setAppName($name): void
    {
        $this->APP_NAME = $name;
        $this->set('APP_NAME', $name);
    }

    /**
     * @return bool If we are in development mode
     */
    public function isDevelopment(): bool
    {
        return str_contains($this->environment(), 'dev');
    }

    public function isDebug()
    {
        return $this->getEnv('DEBUG', true);
    }

    public function isProduction(): bool
    {
        return str_contains($this->environment(), 'prod');
    }

    public function isTesting(): bool
    {
        return str_contains($this->environment(), 'test');
    }

    public function environment(): string
    {
        return $this->env->get('APP_ENV') ?? 'development';
    }

    public function os(): string
    {
        return PHP_OS_FAMILY;
    }

    public function isWindows(): bool
    {
        return $this->os() === 'Windows';
    }

    public function isLinux(): bool
    {
        return $this->os() === 'Linux';
    }

    public function isMac(): bool
    {
        return $this->os() === 'Darwin';
    }

    public function isConsoleApp(): bool
    {
        return $this->applicationType === PioniaApplicationType::CONSOLE;
    }

    public function isRestApp(): bool
    {
        return $this->applicationType === PioniaApplicationType::REST;
    }
}
