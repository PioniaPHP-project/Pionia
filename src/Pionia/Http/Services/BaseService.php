<?php

namespace Pionia\Pionia\Http\Services;

use Exception;
use Pionia\Exceptions\ResourceNotFoundException;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Base\Utils\Microable;
use Pionia\Pionia\Contracts\ServiceContract;
use Pionia\Pionia\Http\Request\Request;
use Pionia\Pionia\Utilities\Arrayable;
use Pionia\Response\BaseResponse;
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

    private PioniaApplication $app;

    public function __construct(PioniaApplication $app)
    {
        $this->app = $app;
    }

    /**
     * @var Request $request The request object
     */
    public Request $request;

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
     * @param Request $request The request object
     * @return BaseResponse The response object
     * @throws Exception
     * @internal
     */
    public function processAction(Request $request): BaseResponse
    {
        $this->request = $request;

        $data = Arrayable::toArrayable($request->getData());

        if (!$data->has('SERVICE')){
            throw new Exception("Service undefined!");
        }

        if (!$data->has("ACTION")){
            throw new Exception("Action undefined!");
        }

        $service= $data->get("SERVICE");
        $action = $data->get("ACTION");

        if ($this->serviceRequiresAuth) {
            $this->mustAuthenticate($this->authMessage ?? "Service $service requires authentication");
        }

        if (in_array($action, $this->deactivatedActions)) {
            throw new Exception("Action $action is currently deactivated.");
        }

        if (in_array($action, $this->actionsRequiringAuth)) {
            $this->mustAuthenticate("Action $action requires authentication");
        }

        $files = $request->files;

        // all actions must end with the work Action, otherwise, they will be treated as just helper methods
        $withActionKey = str_ends_with($action, "Action") ? $action : $action."Action";

        if (method_exists($this, $withActionKey) || $this->hasMacro($withActionKey)) {
            // its a normal here, we do the checks and load it normally
            if (isset($this->actionPermissions[$action]) || isset($this->actionPermissions[$withActionKey])) {
                $toCheck = $this->actionPermissions[$action] ?? $this->actionPermissions[$withActionKey];
                if (is_array($toCheck)){
                    $this->canAll($toCheck);
                }
                // from version 1.1.4, we started checking permissions that are also strings, not arrays
                if (is_string($toCheck)){
                    $this->can($toCheck);
                }
            }

            // load it as a macro
            if ($this->hasMacro($withActionKey)){
                $response = $this->$withActionKey($data, $files, $request);
            } else {
                // this is a normal action, we call it normally
                $reflection = new ReflectionMethod($this, $withActionKey);
                $reflection->setAccessible(true);
                $response = $reflection->invoke($this, $data, $files, $request);
            }

            if (is_subclass_of($response, BaseResponse::class)){
                return $response;
            }

            throw new Exception("$withActionKey did not return a correct BaseResponse object. Did your return BaseResponse::jsonResponse?");
        }
        throw new ResourceNotFoundException("Action $action not found in the $service context");
    }
}
