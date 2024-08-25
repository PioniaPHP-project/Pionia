<?php

namespace Pionia\Pionia\Contracts;

interface BaseSwitchContract
{

    /**
     * This method must be implemented by the child class to return an array of services.
     *
     * The array should be in the format of `['SERVICE_NAME' => new SERVICE_CLASS()]`.
     * The SERVICE_NAME is the name that will be used in the request data to call the service.
     * The SERVICE_CLASS is the class that will be called when the SERVICE_NAME is called.
     * The SERVICE_CLASS must extend the BaseRestService class.
     * @version 1.1.7 array can be in the format of `['SERVICE_NAME' => SERVICE_CLASS::class]`.

     *
     * @example
     * ```php
     * public function registerServices() :array
     * {
     *    return [
     *       'service1' => new Service1(),
     *       'service2' => new Service2(),
     *     ];
     * }
     * // version 1.1.7 and above, you can also do the following
     * public function registerServices() :array
     * {
     *   return [
     *     'service1' => Service1::class,
     *    'service2' => Service2::class,
     *   ];
     * }
     * ```
     * @return array The array of services
     */
     public function registerServices() :array;

}
