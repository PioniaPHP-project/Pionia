<?php

namespace Pioneer\core;

use Pioneer\exceptions\ResourceNotFoundException;
use Pioneer\request\Request;
use Pioneer\response\BaseResponse;
use ReflectionException;

/**
 * This is the base class for the API service switch. It is used to switch between different services based on the request data.
 *
 * The child class must implement the registerServices method to return an array of services.
 * It requires the request to define the `SERVICE` key in the request data and the `ACTION` key to define the action to be performed.
 *
 * The SERVICE is the class that will be called when the SERVICE_NAME is called.
 * The ACTION is the method that will be called on the SERVICE.
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 * @see BaseResponse for the response returned by this swicher's processServices method
 *
 */
abstract class BaseApiServiceSwitch
{

    /**
     * This method must be implemented by the child class to return an array of services.
     *
     * The array should be in the format of `['SERVICE_NAME' => new SERVICE_CLASS()]`.
     * The `SERVICE_NAME` is the key that will be used to call the service.
     * The `SERVICE_CLASS` is the class that will be called when the SERVICE_NAME is called.
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
     * ```
     * @return array The array of services
     */
    abstract public function registerServices() :array;

    /**
     * This method checks the request data for the `SERVICE` key and processes the service based on it
     *
     * @param Request $request The request object
     * @throws ResourceNotFoundException|ReflectionException if the SERVICE key is not found, or the service is invalid, or the service is not found
     */
    public static function processServices(Request $request): BaseResponse
    {
        $service = $request->getData()['SERVICE'];
        $action = $request->getData()['ACTION'];

        if (empty($service)) {
            throw new ResourceNotFoundException("Service not defined in request data");
        }
        $klass = new static();
        $services = $klass->registerServices();
        if (array_key_exists($service, $services)) {
            $service = $services[$service];
            if (!is_a($service, 'Pioneer\request\BaseRestService', true)){
                throw new ResourceNotFoundException("Service $service is not a valid service");
            }
            if (empty($action)) {
                throw new ResourceNotFoundException("Action not defined in request data");
            }
            return $service->processAction($action, $request);
        }
        throw new ResourceNotFoundException("Service $service not found");
    }
}
