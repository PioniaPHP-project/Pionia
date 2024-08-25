<?php

namespace Pionia\Pionia\Http\Switches;

use Pionia\Pionia\Contracts\ApplicationContract;
use Pionia\Pionia\Exceptions\UserUnauthenticatedException;
use Pionia\Pionia\Exceptions\UserUnauthorizedException;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Contracts\BaseSwitchContract;
use Pionia\Pionia\Http\Request\Request;
use Pionia\Pionia\Http\Response\BaseResponse;
use ReflectionException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

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
abstract class BaseApiServiceSwitch implements BaseSwitchContract
{
    /**
     * This is the application object
     * @var PioniaApplication
     */
    protected PioniaApplication $app;

    /**
     * This method checks the request data for the `SERVICE` key and processes the service based on it
     *
     * @param Request $request The request object
     * @param ApplicationContract $app The application instance
     * @return BaseResponse The response object
     * @throws Throwable
     */
    private static function processServices(Request $request, ApplicationContract $app): BaseResponse
    {
        $data = $request->getData();

        $service = $data->getOrThrow('service', new ResourceNotFoundException("Service not defined in request data"));
        $action = $data->getOrThrow('action',  new ResourceNotFoundException("Action not defined in request data"));

        $klass = new static();
        $registeredServicesList = arr($klass->registerServices() ?? []);

        $serviceKlass = $registeredServicesList->get($service);

        // if the class was defined as a string especially using Service::class, we instantiate it
        if ($serviceKlass && is_string($serviceKlass)){
            $serviceKlass = $app->context->make($serviceKlass, ['app' => $app, 'request' => $request]);
        }

        if ($serviceKlass) {
//            if (!is_a($serviceKlass, 'Pionia\Pionia\Http\Services\BaseRestService', true)){
//                throw new ResourceNotFoundException("Service $service is not a valid service");
//            }
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
     * @return BaseResponse
     * @throws Throwable
     */
    public static function processor(Request $request): BaseResponse
    {
        $app = $request->getApplication();
        $codes = $app->getSilently('env');

        try {
            return self::processServices($request, $app);
        } catch (ResourceNotFoundException $e) {
            $nofFount = $codes->get("not_found_code") ?? 404;
            return BaseResponse::JsonResponse($nofFount, $e->getMessage());
        } catch (UserUnauthenticatedException $e) {
            $auth = $codes->get("unauthenticated_code") ?? 401;
            return BaseResponse::JsonResponse($auth, $e->getMessage());
        } catch (ReflectionException $e) {
            $serverError = $codes->get("server_error_code") ?? 500;
            return BaseResponse::JsonResponse($serverError, $e->getMessage());
        } catch (UserUnauthorizedException $e) {
            $unauth = $codes->get("unauthorized_code") ?? 403;
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
        $app = $request->getApplication();
        return response(0, 'pong', [
            'framework' => $app->getName(),
            'version'=> $app->getVersion(),
            'port' => $request->getPort(),
            'uri' => $request->getRequestUri(),
            'schema' => $request->getScheme(),
        ]);
    }
}
