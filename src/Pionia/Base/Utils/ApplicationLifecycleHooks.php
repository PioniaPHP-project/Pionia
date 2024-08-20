<?php

namespace Pionia\Pionia\Base\Utils;

trait ApplicationLifecycleHooks
{

    /**
     * Register the application's boot hook listeners.
     *
     * All logic that needs to run before the application is booted
     *
     * @return void
     */
    public function boot(): void
    {
    }

    /**
     * Register the application's booted hook listeners.
     *
     * All logic that needs to run after the application is booted
     *
     * @return void
     */
    public function booted(): void
    {
    }

    /**
     * Regester the application's terminating hook listeners.
     *
     * All logic that needs to run before the application is terminated
     *
     * @return void
     */
    public function terminating()
    {
    }


    /**
     * Register the application's terminated hook listeners.
     *
     * All logic that needs to run after the application is terminated
     *
     * @return void
     */
    public function terminated()
    {
    }
}
