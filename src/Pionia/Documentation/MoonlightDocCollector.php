<?php

namespace Pionia\Documentation;

use Pionia\Collections\Arrayable;
use Pionia\Documentation\Contracts\MoonlightApiCatalog;
use Pionia\Documentation\Contracts\ServiceDoc;
use Pionia\Realm\AppRealm;
use ReflectionClass;

class MoonlightDocCollector
{
    public function __construct(
        private readonly MoonlightDocParser $parser = new MoonlightDocParser(),
    ) {
    }

    public function collect(?AppRealm $app = null): MoonlightApiCatalog
    {
        $app ??= app();
        $app->make(AppRealm::WEB_APP_TAG)->bootOnce();

        $switches = $app->getSilently(AppRealm::SWITCHES_TAGS);
        $services = $app->getSilently(AppRealm::SERVICES_TAG);

        $switchMap = $switches instanceof Arrayable ? $switches->all() : (array) ($switches ?? []);
        $serviceMap = $services instanceof Arrayable ? $services->all() : (array) ($services ?? []);

        $versions = [];

        foreach ($switchMap as $version => $switchClass) {
            $controller = $switchClass . '::processor';
            $registered = $serviceMap[$controller] ?? [];

            if (!is_array($registered)) {
                continue;
            }

            foreach ($registered as $alias => $serviceClass) {
                if (!is_string($serviceClass) || !class_exists($serviceClass)) {
                    continue;
                }

                $versions[$version][$alias] = $this->documentService(
                    $version,
                    $alias,
                    $serviceClass,
                );
            }
        }

        ksort($versions);

        return new MoonlightApiCatalog(
            title: (string) ($app->getOrDefault('APP_NAME', 'Pionia App') ?? 'Pionia App'),
            versions: $versions,
        );
    }

    private function documentService(string $version, string $alias, string $serviceClass): ServiceDoc
    {
        $reflection = new ReflectionClass($serviceClass);
        $tags = $this->parser->parseServiceTags($reflection);

        $defaults = $reflection->getDefaultProperties();
        $deactivated = $defaults['deactivatedActions'] ?? [];
        $requiringAuth = $defaults['actionsRequiringAuth'] ?? [];
        $serviceRequiresAuth = (bool) ($defaults['serviceRequiresAuth'] ?? false);
        $actionPermissions = $defaults['actionPermissions'] ?? [];
        $table = $defaults['table'] ?? ($tags['table'] ?? null);

        $actions = $this->parser->parseServiceActions(
            $reflection,
            $deactivated,
            $requiringAuth,
            $serviceRequiresAuth,
            $actionPermissions,
        );

        return new ServiceDoc(
            version: $tags['version'] ?? $version,
            alias: $tags['service'] ?? $alias,
            className: $serviceClass,
            summary: $tags['summary'] ?? null,
            auth: $tags['auth'] ?? ($serviceRequiresAuth ? 'required' : 'partial'),
            table: is_string($table) ? $table : null,
            actions: $actions,
        );
    }
}
