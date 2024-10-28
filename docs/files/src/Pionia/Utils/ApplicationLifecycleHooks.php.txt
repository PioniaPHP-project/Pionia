<?php

namespace Pionia\Utils;

use Closure;
use JetBrains\PhpStorm\NoReturn;
use Pionia\Collections\Arrayable;

trait ApplicationLifecycleHooks
{
    /**
     * Register callbacks to be run after the application is booted
     * This method can be called multiple times to add more hooks
     *
     * Hooks here have access to the application instance
     * @param Closure $closure
     * @return static
     */
    public function booted(Closure $closure): static
    {
        $this->bootedCallbacks[] = $closure;
        return $this;
    }

    /**
     * Register hooks to mutate the application booting cycle.
     * Runs before the application is booted
     * Can be called multiple times to add more hooks.
     *
     * All hooks here do not have access to the application instance
     * @param Closure $callback
     * @return $this
     */
    public function booting(Closure $callback): static
    {
        $this->bootingCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register the application's terminating hooks.
     * All logic that needs to run before the application is terminated
     * Can be called multiple times to add more hooks
     *
     * Hooks here have access to the application instance
     * @param Closure $callback
     * @return void
     */
    public function terminating(Closure $callback): void
    {
        $this->terminatingCallbacks[] = $callback;
    }


    /**
     * Register the application's terminated hook listeners.
     * All logic that needs to run after the application is terminated
     * Can be called multiple times to add more hooks
     *
     * Hooks here do not have access to the application instance
     * @param Closure $closure
     * @return void
     */
    public function terminated(Closure $closure): void
    {
        $this->terminatedCallbacks[] = $closure;
    }

    /**
    * Run all booted callbacks
    * @return void
    */
    private function callBootedCallbacks(): void
    {
        if (count($this->bootedCallbacks) > 0 ) {
            array_map(function ($callback) {
                return $callback($this);
            }, $this->bootedCallbacks);
        }
    }

    /**
     * Run all booting callbacks
     */
    private function callBootingCallbacks(): void
    {
        if (count($this->bootingCallbacks) > 0) {
            array_map(function ($callback) {
                return $callback();
            }, $this->bootingCallbacks);
        }
    }

    /**
     * Run all terminating callbacks
     */
    private function callTerminatingCallbacks(): void
    {
        if (count($this->terminatingCallbacks) > 0) {
            array_map(function ($callback) {
                return $callback($this);
            }, $this->terminatingCallbacks);
        }
    }

    /**
     * Run all terminated callbacks
     */
    private function callTerminatedCallbacks(): void
    {
        if (count($this->terminatedCallbacks) > 0 ) {
            array_map(function ($callback) {
                return $callback();
            }, $this->terminatedCallbacks);
        }
    }

    /**
     * Shutdown the application
     * @param int $status
     * @return void
     */
    #[NoReturn]
    public function terminate(int $status = 1): void
    {
        $this->report('info', 'Shutting down the application');

        $this->callTerminatingCallbacks();
        // run the termination hook on each provider
        if ($this->appProviders->isFilled()) {
            $this->appProviders->each(function ($provider) {
                $this->contextMakeSilently($provider, ['app' => $this])->onTerminate();
            });
        }
        $this->context = null;
        $this->env = null;
        $this->envResolver = null;
        $this->applicationType = null;
        $this->logger = null;
        $this->booted = false;
        $this->bootedCallbacks = [];
        $this->terminatingCallbacks = [];
        $this->bootingCallbacks = [];
        $this->callTerminatedCallbacks();
        $this->terminatedCallbacks = [];
        $this->appProviders = new Arrayable();
        exit($status);
    }

    /**
     * Shutdown the application
     * @return void
     */
    #[NoReturn]
    public function shutdown(): void
    {
        $this->logger?->info('Registering terminating callback');

        $this->terminate();
    }
}
