<?php

namespace jetPhp\core;

use jetPhp\core\helpers\Utilities;
use jetPhp\exceptions\ResourceNotFoundException;
use jetPhp\request\Request;

abstract class BaseApiServiceSwitch
{

    /**
     * This method must be implemented by the child class to return an array of services
     *
     * The array should be in the format of ['SERVICE_NAME' => new SERVICE_CLASS()]
     * The SERVICE_NAME is the key that will be used to call the service
     * The SERVICE_CLASS is the class that will be called when the SERVICE_NAME is called
     * @return array
     */
    abstract public static function registerServices() :array;

    /**
     * This method checks the request data for the `SERVICE` key and processes the service based on it
     *
     * @param Request $request The request object
     * @throws ResourceNotFoundException if the SERVICE key is not found, or the service is invalid, or the service is not found
     */
    public function processServices(Request $request)
    {
        $service = $request->getData()['SERVICE'];
        $action = $request->getData()['ACTION'];

        if (empty($service)) {
            throw new ResourceNotFoundException("Service not defined in request data");
        }

        $services = $this::registerServices();
        if (array_key_exists($service, $services)) {
            $service = $services[$service];
            if (!is_a($service, 'jetPhp\request\BaseRestService', true)){
                throw new ResourceNotFoundException("Service $service is not a valid service");
            }
            if (empty($action)) {
                throw new ResourceNotFoundException("Action not defined in request data");
            }
            return $service->process($action, $request);
        }
        throw new ResourceNotFoundException("Service $service not found");
    }
}
