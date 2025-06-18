<?php

namespace Pionia\Realm;

use Carbon\Callback;
use DI\Container;
use Psr\Container\ContainerInterface;

/**
 * We need to separate the DI from the app instance itself
 * Any early boot bindings can be added at this level
 */
class RealmManager implements RealmContract
{
    private ?ContainerInterface $instance = null;

    private array $bootingBindings = [];
    private array $bootedBindings = [];

    private ?string $appPath = null;

    public function __construct(string $appPath)
    {
        $this->appPath = $appPath;
    }

    public function boot(): ContainerInterface
    {
        if (!$this->instance) {
            $this->resolvedBootingCallbacks();
            $this->instance = new Container();
        }
        $this->resolvedBootedCallbacks($this->instance);
        $this->instance->set('app.path', $this->appPath);
        return $this->instance;
    }


    /**
     * Runs before
     * @param Callback $callback
     * @return $this
     */
    public function booting(Callback $callback): RealmManager
    {
        $this->bootingBindings[] = $callback;
        return $this;
    }

    private function resolvedBootingCallbacks(): void
    {
        arr($this->bootingBindings)->each(function ($callback) {
            call_user_func($callback);
        });
    }

    private function resolvedBootedCallbacks($instance): void
    {
        arr($this->bootedBindings)->each(function ($callback) use ($instance) {
            $callback($instance);
        });
    }

    public function booted(Callback $callback): RealmManager
    {
        $this->bootedBindings[] = $callback;
        return $this;
    }
}
