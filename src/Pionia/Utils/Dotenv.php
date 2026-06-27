<?php

namespace Pionia\Utils;

use InvalidArgumentException;

/**
 * Loads .env files and populates $_ENV / $_SERVER (Symfony Dotenv replacement).
 */
final class Dotenv
{
    /** @var list<string> */
    private array $loadedKeys = [];

    public function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $values = $this->parseFile($path);

        foreach (array_keys($values) as $name) {
            if (!in_array($name, $this->loadedKeys, true)) {
                $this->loadedKeys[] = $name;
            }
        }

        $this->populate($values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function populate(array $values, bool $overrideExistingVars = false): void
    {
        foreach ($values as $name => $value) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            if (!$overrideExistingVars && $this->isSetInEnvironment($name)) {
                continue;
            }

            $this->set($name, $value);
        }

        $this->syncLoadedKeys();
    }

    /**
     * @return array<string, string>
     */
    private function parseFile(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('Unable to read env file "%s".', $path));
        }

        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            $values[$name] = $this->parseValue($value);
        }

        return $values;
    }

    private function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        if (($first === '"' || $first === "'") && str_ends_with($value, $first)) {
            $value = substr($value, 1, -1);
        }

        return str_replace(['\\n', '\\r'], ["\n", "\r"], $value);
    }

    private function isSetInEnvironment(string $name): bool
    {
        $existing = getenv($name, true);

        return $existing !== false;
    }

    private function set(string $name, mixed $value): void
    {
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;

        if (is_scalar($value) || $value === null) {
            putenv($name . '=' . (string) $value);
        }

        if (!in_array($name, $this->loadedKeys, true)) {
            $this->loadedKeys[] = $name;
        }
    }

    private function syncLoadedKeys(): void
    {
        $keys = implode(',', $this->loadedKeys);
        $_ENV['SYMFONY_DOTENV_VARS'] = $keys;
        $_SERVER['SYMFONY_DOTENV_VARS'] = $keys;
        $_ENV['PIONIA_ENV_VARS'] = $keys;
        $_SERVER['PIONIA_ENV_VARS'] = $keys;
    }
}
