<?php

namespace Pionia\Http\Services;

use Exception;
use Pionia\Exceptions\ResourceNotFoundException;
use Pionia\Base\PioniaApplication;
use Pionia\Contracts\ServiceContract;
use Pionia\Http\Request\Request;
use Pionia\Utils\Microable;
use Pionia\Http\Response\BaseResponse;
use Pionia\Utils\Support;
use ReflectionMethod;

/**
 * This is the main class all other services must extend.
 * It contains the basic methods that all services will need for authentication and request processing
 *
 * @property Request $request The request object
 * @property array $deactivatedActions An array of actions that are deactivated for the current service
 * @property array $actionsRequiringAuth An array of actions that require authentication
 * @property bool $serviceRequiresAuth If true, the entire service requires authentication
 * @property string | null $authMessage This message will be displayed when the entire service requires authentication
 * @internal
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 **/
class BaseService implements ServiceContract
{
    use AuthTrait, RequestActionTrait, Microable;

    public PioniaApplication $app;
    public Request $request;

    public function __construct(PioniaApplication $app, Request $request)
    {
        $this->app = $app;
        $this->request = $request;
    }

    /**
     * An array of actions that are deactivated for the current service
     * @var array $deactivatedActions
     */
    public array $deactivatedActions = [];

    /**
     * An associative array of actions and their required permissions.
     * The permissions will be checked on the context user object
     * @example
     * ```php
     * public array $actionPermissions = [
     * 'create' => ['create_article'],
     * 'delete' => ['delete_article'],
     * 'update' => ['update_article'],
     * 'list' => ['list_article'],
     * 'get' => ['get_article'],
     * ]
     * @var array $actionPermissions
     */
    public array $actionPermissions = [];

    /**
     * This array contains the actions that require authentication
     * @example ```php
     * public array $actionsRequiringAuth = ['create', 'delete', 'update'];
     * ```
     *
     * All the actions defined in this will only be access by only authenticated requests based on the user object
     * @var array $actionsRequiringAuth
     */
    public array $actionsRequiringAuth = [];

    /**
     * If true, the entire service requires authentication.
     *
     * No action in the service will be accessible without authentication
     * @var bool $serviceRequiresAuth
     */
    public bool  $serviceRequiresAuth = false;

    /**
     * This message will be displayed when the entire service requires authentication.
     * It is used to inform the user why they cannot access the service.
     * By default, this will return `Service $service requires authentication`
     * @var ?string $authMessage
     */
    public ?string $authMessage = null;

    /**
     * This method is called when the service is called with an action
     *
     * @return BaseResponse The response object
     * @throws Exception
     * @internal
     */
    public function processAction(string $action, string $service): BaseResponse
    {
        $data = $this->request->getData();

        if ($this->serviceRequiresAuth) {
            $this->mustAuthenticate($this->authMessage ?? "Service $service requires authentication");
        }

        if (in_array($action, $this->deactivatedActions)) {
            throw new Exception("Action $action is currently deactivated.");
        }

        if (in_array($action, $this->actionsRequiringAuth)) {
            $this->mustAuthenticate("Action $action requires authentication");
        }

        $files = $this->request->files;

        if(!(str_contains($action, 'Action') || str_contains($action, 'action'))){
            $action = $action . 'Action';
        }

        $action = Support::toCamelCase($action);

        if (!method_exists($this, $action)){
            throw new ResourceNotFoundException("Action $action not found in the $service context");
        }


        // its a normal here, we do the checks and load it normally
        if (isset($this->actionPermissions[$action])) {
            $toCheck = $this->actionPermissions[$action];
            if (is_array($toCheck)){
                $this->canAll($toCheck);
            }
            // from version 1.1.4, we started checking permissions that are also strings, not arrays
            if (is_string($toCheck)){
                $this->can($toCheck);
            }
        }

        // load it as a macro
        if ($this->hasMacro($action)){
            $response = $this->$action($data, $files, $this->request);
        } else {
            // this is a normal action, we call it normally
            $reflection = new ReflectionMethod($this, $action);
            $reflection->setAccessible(true);
            $response = $reflection->invoke($this, $data, $files, $this->request);
        }

        if (is_a($response, BaseResponse::class)){
            return $response;
        }

        throw new Exception("$action did not return a correct BaseResponse object. Did your return `response()`?");
    }
}
