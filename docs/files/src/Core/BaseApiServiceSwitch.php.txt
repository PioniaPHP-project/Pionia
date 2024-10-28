<?php

namespace Pionia\Core;

use Pionia\Exceptions\ResourceNotFoundException;
use Pionia\Exceptions\UserUnauthenticatedException;
use Pionia\Exceptions\UserUnauthorizedException;
use Pionia\Request\Request;
use Pionia\Response\BaseResponse;
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
    abstract protected function registerServices() :array;

    /**
     * This method checks the request data for the `SERVICE` key and processes the service based on it
     *
     * @param Request $request The request object
     * @return BaseResponse The response object
     * @throws UserUnauthenticatedException if the user is not authenticated and the service requires authentication
     * @throws ResourceNotFoundException|ReflectionException|UserUnauthorizedException if the SERVICE key is not found, or the service is invalid, or the service is not found*@see BaseResponse for the response returned by this swicher's processServices method
     */
    private static function processServices(Request $request): BaseResponse
    {
        $data = $request->getData();
        $service = $data['SERVICE']?? $data['service'] ?? throw new ResourceNotFoundException("Service not defined in request data");
        $action = $data['ACTION']?? $data['action'] ?? throw new ResourceNotFoundException("Action not defined in request data");

        $klass = new static();
        $serviceKlass = $klass->registerServices()[$service]?? null;
        // if the class was defined as a string especially using Service::class, we instantiate it
        if ($serviceKlass && is_string($serviceKlass)){
            $serviceKlass = new $serviceKlass();
        }

        if ($serviceKlass) {
            if (!is_a($serviceKlass, 'Pionia\Pionia\Http\Services\BaseRestService', true)){
                throw new ResourceNotFoundException("Service $service is not a valid service");
            }
            return $serviceKlass->processAction($action, $request);
        }
        throw new ResourceNotFoundException("Service $service not found");
    }

    /**
     * This is the sole action to be called in the routes file. It processes the request and returns the response
     * @param Request $request
     * @return BaseResponse
     */
    public static function processor(Request $request): BaseResponse
    {
        $codes = pionia::getServerSettings();
        try {
            return self::processServices($request);
        } catch (ResourceNotFoundException $e) {
            $nofFount = $codes['NOT_FOUND_CODE'] ?? $codes['not_found_code'] ?? 404;
            return BaseResponse::JsonResponse($nofFount, $e->getMessage());
        } catch (UserUnauthenticatedException $e) {
            $auth = $codes['UNAUTHENTICATED_CODE'] ?? $codes['unauthenticated_code'] ?? 401;
            return BaseResponse::JsonResponse($auth, $e->getMessage());
        } catch (ReflectionException $e) {
            $serverError = $codes['SERVER_ERROR_CODE'] ?? $codes['server_error_code'] ?? 500;
            return BaseResponse::JsonResponse($serverError, $e->getMessage());
        } catch (UserUnauthorizedException $e) {
            $unauth = $codes['UNAUTHORIZED_CODE'] ?? $codes['unauthorized_code'] ?? 403;
            return BaseResponse::JsonResponse($unauth, $e->getMessage());
        }
    }

    /**
     * This is just for checking the api status
     *
     * You can even override it in your own switch class
     */
    public function ping(Request $request): BaseResponse
    {
        return BaseResponse::JsonResponse(0, 'pong', [
            'framework' => pionia::$name,
            'version'=> pionia::$version,
            'port' => $request->getPort(),
            'uri' => $request->getRequestUri(),
            'schema' => $request->getScheme(),
        ]);
    }
}
