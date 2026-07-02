<?php

namespace Pionia\Performance;

use Pionia\Http\Routing\RouteDefinition;
use Pionia\Http\Routing\RouteTable;
use Pionia\Realm\AppRealm;

/**
 * Writes file-backed bootstrap caches for routes and providers.
 */
final class BootstrapCacheGenerator
{
    public function __construct(
        private readonly string $appRoot,
    ) {
    }

    /**
     * @return array{routes: ?string, providers: ?string}
     */
    public function generate(AppRealm $app): array
    {
        $bootstrapDir = $this->bootstrapDirectory();
        if (!is_dir($bootstrapDir)) {
            mkdir($bootstrapDir, 0775, true);
        }

        $routesPath = $this->writeRoutes($app);
        $providersPath = $this->writeProviders($app);

        return [
            'routes' => $routesPath,
            'providers' => $providersPath,
        ];
    }

    public function bootstrapDirectory(): string
    {
        return $this->appRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'bootstrap';
    }

    public function routesCachePath(): string
    {
        return $this->bootstrapDirectory() . DIRECTORY_SEPARATOR . 'routes.php';
    }

    public function providersCachePath(): string
    {
        return $this->bootstrapDirectory() . DIRECTORY_SEPARATOR . 'providers.php';
    }

    /**
     * @return list<string>
     */
    public static function artifactPaths(string $appRoot): array
    {
        $dir = $appRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'bootstrap';

        return [
            $dir . DIRECTORY_SEPARATOR . 'preload.php',
            $dir . DIRECTORY_SEPARATOR . 'routes.php',
            $dir . DIRECTORY_SEPARATOR . 'providers.php',
        ];
    }

    private function writeRoutes(AppRealm $app): ?string
    {
        $routes = $app->getSilently(AppRealm::APP_ROUTES_TAG);
        if (!$routes instanceof RouteTable || count($routes) === 0) {
            return null;
        }

        $export = [];
        foreach ($routes->all() as $name => $route) {
            if (!$route instanceof RouteDefinition) {
                continue;
            }

            $export[$name] = [
                'path' => $route->path(),
                'defaults' => $route->defaults(),
                'requirements' => $route->requirements(),
                'methods' => $route->methods(),
            ];
        }

        if ($export === []) {
            return null;
        }

        $path = $this->routesCachePath();
        $contents = "<?php\n\nreturn " . var_export($export, true) . ";\n";
        file_put_contents($path, $contents);

        return $path;
    }

    private function writeProviders(AppRealm $app): ?string
    {
        $providers = $app->getSilently('app_providers');
        if ($providers === null) {
            return null;
        }

        $export = $providers instanceof \Pionia\Collections\Arrayable
            ? $providers->toArray()
            : (array) $providers;

        if ($export === []) {
            return null;
        }

        $path = $this->providersCachePath();
        $contents = "<?php\n\nreturn " . var_export($export, true) . ";\n";
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @return RouteTable|null
     */
    public static function loadRoutes(string $appRoot): ?RouteTable
    {
        if (!self::bootstrapCacheEnabled($appRoot)) {
            return null;
        }

        $path = $appRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'routes.php';
        if (!is_file($path)) {
            return null;
        }

        $data = require $path;
        if (!is_array($data) || $data === []) {
            return null;
        }

        $table = new RouteTable();
        foreach ($data as $name => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $table->add(
                (string) $name,
                new RouteDefinition(
                    (string) ($definition['path'] ?? '/'),
                    (array) ($definition['defaults'] ?? []),
                    (array) ($definition['requirements'] ?? []),
                    (array) ($definition['methods'] ?? []),
                ),
            );
        }

        return count($table) > 0 ? $table : null;
    }

    /**
     * @return array<string, string>|null
     */
    public static function loadProviders(string $appRoot): ?array
    {
        if (!self::bootstrapCacheEnabled($appRoot)) {
            return null;
        }

        $path = $appRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'providers.php';
        if (!is_file($path)) {
            return null;
        }

        $data = require $path;

        return is_array($data) && $data !== [] ? $data : null;
    }

    public static function bootstrapCacheEnabled(string $appRoot): bool
    {
        $settings = PreloadManifest::fromSettings($appRoot);

        if ($settings['bootstrap_cache']) {
            return true;
        }

        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');

        return is_string($env) && strtolower($env) === 'production';
    }
}
