<?php

namespace Pionia\Http\Switches;

use Exception;
use Pionia\Exceptions\UserUnauthenticatedException;
use Pionia\Exceptions\UserUnauthorizedException;
use Pionia\Contracts\BaseSwitchContract;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Response\Response;
use Pionia\Http\Routing\SupportedHttpMethods;
use Pionia\Utils\CachedEndpoints;
use ReflectionException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

/**
 * This is the base class for the APIRoute service switch. It is used to switch between different services based on the request data.
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
abstract class BaseApiServiceSwitch implements BaseSwitchContract
{
    use CachedEndpoints;

    /**
     * This method checks the request data for the `SERVICE` key and processes the service based on it
     *
     * @param Request $request The request object
     * @return BaseResponse The response object
     * @throws Throwable
     */
    private static function processServices(Request $request): BaseResponse
    {
        // if we had the response cached, we return it
        try {
            $cachedResponse = self::cacheResponse($request);
            if ($cachedResponse) {
                return $cachedResponse;
            }
        } catch (Exception $e) {
            logger()->warning($e);
        }

        if ($request->isMethod(SupportedHttpMethods::GET)){
            $data = $request->attributes;
            $service = $data->getString("service") ?? throw new ResourceNotFoundException("Service not defined in request data");
            $action = $data->getString("action") ?? throw new ResourceNotFoundException("Action not defined in request data");
        } else {
            $data = $request->getData();
            $service = $data->getOrThrow('service', new ResourceNotFoundException("Service not defined in request data"));
            $action = $data->getOrThrow('action',  new ResourceNotFoundException("Action not defined in request data"));
        }

        $controller = get_called_class()."::processor";
        $serviceKlass = services($controller)[$service];

        // if the class was defined as a string especially using Service::class, we instantiate it
        if ($serviceKlass && is_string($serviceKlass)){
            $serviceKlass = container()->make($serviceKlass, ['request' => $request]);
        }

        if ($serviceKlass) {
            if (method_exists($serviceKlass, 'processAction')){
                return $serviceKlass->processAction($action, $service);
            } else {
                throw new ResourceNotFoundException("Service $service is not a valid service");
            }

        }
        throw new ResourceNotFoundException("Service $service not found");
    }

    /**
     * This is the sole action to be called in the routes file. It processes the request and returns the response
     * @param Request $request
     * @throws Throwable
     */
    public static function processor(Request $request): Response
    {
        try {
            $response =  self::processServices($request);
        } catch (ResourceNotFoundException $e) {
            $response = response(env("not_found_code", 404), $e->getMessage());
        } catch (UserUnauthenticatedException $e) {
            $response = response(env("unauthenticated_code", 401), $e->getMessage());
        } catch (ReflectionException $e) {
            $response = response(env("server_error_code", 500), $e->getMessage());
        } catch (UserUnauthorizedException $e) {
            $response = response(env("unauthorized_code", 403), $e->getMessage());
        }
        logger()->debug($response->getPrettyResponse());
        return new Response($response->getPrettyResponse(), 200);
    }

    /**
     * This is just for checking the api status
     *
     * You can even override it in your own switch class
     */
    public static function ping(Request $request): Response
    {
        $data = [
            'framework' => app()->getAppName(),
            'version'=> app()->phpVersion(),
            'port' => $request->getPort(),
            'uri' => $request->getRequestUri(),
            'schema' => $request->getScheme(),
        ];

        $response =  response(0, 'pong', isDebug() ? $data : null);
        logger()->info($response->getPrettyResponse());
        return new Response($response->getPrettyResponse(), 200);
    }
}
