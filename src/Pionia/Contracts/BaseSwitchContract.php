<?php

namespace Pionia\Contracts;

use Pionia\Collections\Arrayable;

interface BaseSwitchContract
{

    /**
     * This method must be implemented by the child class to return an array of services.
     *
     * The array should be in the format of `['SERVICE_NAME' => SERVICE_NAME::class]`.
     * The SERVICE_NAME is the name that will be used in the request data to call the service.
     * The SERVICE_CLASS is the class that will be called when the SERVICE_NAME is called.
     * The SERVICE_CLASS must extend the BaseRestService class.
     * @return Arrayable The array of services
     * @example
     * ```php
     * public function registerServices() :array
     * {
     *   return arr([
     *     'service1' => Service1::class,
     *    'service2' => Service2::class,
     *   ]);
     * }
     * ```
     * @version 1.1.7 array can be in the format of `['SERVICE_NAME' => SERVICE_CLASS::class]`.
     *
     */
     public function registerServices() :Arrayable;

}
