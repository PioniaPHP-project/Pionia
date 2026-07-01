<?php

namespace Application\Providers;

use Pionia\Base\Provider\Provider;
use Pionia\Exceptions\ResourceNotFoundException;

/**
 * Application-level hooks: exception maps, logging channels, container bindings.
 *
 * Register in bootstrap/application.php:
 *   pionia()->addAppProvider(AppProvider::class);
 *
 * Or in environment/settings.ini under [app_providers].
 */
class AppProvider extends Provider
{
    public function configureExceptions(\Pionia\Exceptions\ExceptionPipeline $exceptions): void
    {
        $exceptions->map(ResourceNotFoundException::class, fn (ResourceNotFoundException $e) => response(404, $e->getMessage()));
    }

    public function onBooted(): void
    {
        // Example: realm()->set('billing.enabled', env('BILLING_ENABLED', false));
    }
}
