<?php

namespace Pionia\Http\Pages;

use Pionia\Collections\Arrayable;
use Pionia\Documentation\MoonlightDocCollector;
use Symfony\Component\Routing\Route;

/**
 * Shared developer-context tables (environment, routes, stack, services).
 */
class DeveloperContextData
{
    /**
     * @return list<array{0: string, 1: string}>
     */
    public function environmentRows(bool $hideEnvList = false): array
    {
        if ($hideEnvList || $this->welcomeFlag('HIDE_ENV')) {
            return [];
        }

        $rows = [];
        foreach ($this->envKeys() as $key) {
            if ($key === '') {
                continue;
            }

            $value = env($key);
            $display = $this->isSensitiveEnvKey($key)
                ? '<span class="text-muted">••••••••</span>'
                : $this->formatCellValue($value);

            $rows[] = ['<code>' . $this->e($key) . '</code>', $display];
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function routeRows(): array
    {
        $rows = [];

        foreach (allRoutes()->all() as $name => $route) {
            if (!$route instanceof Route) {
                continue;
            }

            $controller = $route->getDefaults()['_controller'] ?? '—';
            $methods = implode(', ', $route->getMethods());
            $detail = '<code>' . $this->e($route->getPath()) . '</code>'
                . '<div class="small debug-detail mt-1">' . $this->e($methods) . ' · ' . $this->e((string) $controller) . '</div>';

            $rows[] = [$this->e((string) $name), $detail];
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function keyValueRows(mixed $collection): array
    {
        $rows = [];
        $items = $collection instanceof Arrayable ? $collection->all() : (array) $collection;

        foreach ($items as $key => $value) {
            $rows[] = [$this->e((string) $key), '<code>' . $this->e($this->stringifyValue($value)) . '</code>'];
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function serviceRows(): array
    {
        try {
            $catalog = (new MoonlightDocCollector())->collect();
        } catch (\Throwable) {
            return [];
        }

        $rows = [];

        foreach ($catalog->versions as $version => $services) {
            foreach ($services as $alias => $service) {
                $actionNames = array_map(static fn ($action) => $action->name, $service->actions);
                $detail = '<code>' . $this->e($service->className) . '</code>'
                    . '<div class="small debug-detail mt-1">' . $this->e(implode(', ', $actionNames)) . '</div>';

                $rows[] = [$this->e($version . ' / ' . $alias), $detail];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private function envKeys(): array
    {
        $keys = envKeys();
        if ($keys !== []) {
            return array_values(array_filter(array_map('trim', $keys)));
        }

        $merged = array_merge(array_keys($_ENV), array_keys($_SERVER));

        return array_values(array_unique(array_filter($merged, static fn (string $key): bool => !str_starts_with($key, 'SYMFONY_'))));
    }

    private function welcomeFlag(string $key): bool
    {
        $welcome = env('welcome', []);
        if (!is_array($welcome) || !array_key_exists($key, $welcome)) {
            return false;
        }

        return filter_var($welcome[$key], FILTER_VALIDATE_BOOLEAN);
    }

    private function isSensitiveEnvKey(string $key): bool
    {
        $hidden = ['password', 'pass', 'pin', 'token', 'secret', 'pwd', 'credential', 'cvv'];
        $logging = env('logging', []);
        if (is_array($logging) && !empty($logging['HIDE_IN_LOGS'])) {
            $hidden = array_merge($hidden, array_map('trim', explode(',', (string) $logging['HIDE_IN_LOGS'])));
        }

        $lower = strtolower($key);
        foreach ($hidden as $needle) {
            if ($needle !== '' && str_contains($lower, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function formatCellValue(mixed $value): string
    {
        if (is_array($value) || $value instanceof Arrayable) {
            $encoded = json_encode($value instanceof Arrayable ? $value->all() : $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            return '<pre class="mb-0 small">' . $this->e((string) $encoded) . '</pre>';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '<span class="text-muted">null</span>';
        }

        return $this->e((string) $value);
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return (string) json_encode($value);
        }

        if ($value instanceof Arrayable) {
            return json_encode($value->all()) ?: '';
        }

        return is_object($value) ? $value::class : gettype($value);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
