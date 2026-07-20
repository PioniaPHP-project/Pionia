<?php

namespace Pionia\Http\Services;

use Exception;
use Pionia\Auth\ActionAuthResolver;
use Pionia\Exceptions\ResourceNotFoundException;
use Pionia\Contracts\ServiceContract;
use Pionia\Http\Request\Request;
use Pionia\Utils\Microable;
use Pionia\Http\Response\ApiResponse;
use Pionia\Utils\Support;
use Pionia\Validations\ActionValidationResolver;

/**
 * This is the main class all other services must extend.
 * It contains the basic methods that all services will need for authentication and request processing
 *
 * Document actions with `@moonlight-*` PHPDoc tags or `#[MoonlightAction]` — see `docs/MOONLIGHT-DOCS.md`.
 * Protect actions with `#[Authenticated]`, `#[Can]`, `#[CanAny]` — see `Pionia\Auth\Attributes`.
 * Generate API reference: `pionia api:docs`.
 *
 * @property Request $request The request object
 * @property array $deactivatedActions An array of actions that are deactivated for the current service
 * @property array $actionsRequiringAuth An array of actions that require authentication
 * @property bool $serviceRequiresAuth If true, the entire service requires authentication
 * @property string | null $authMessage This message will be displayed when the entire service requires authentication
 * @internal
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 **/
class AbstractService implements ServiceContract
{
    use AuthTrait, RequestActionTrait, Microable;

    public Request $request;

    public function __construct(Request $request)
    {
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
     * @param string $action
     * @param string $service
     * @internal
     */
    public function processAction(string $action, string $service): ApiResponse
    {
        $data = $this->request->getData();
        $requestAction = $action;

        if (in_array($action, $this->deactivatedActions)) {
            throw new Exception("Action $action is currently deactivated.");
        }

        $files = $this->request->files;

        if(!(str_contains($action, 'Action') || str_contains($action, 'action'))){
            $action = $action . 'Action';
        }

        $action = Support::toCamelCase($action);

        if (!method_exists($this, $action)){
            throw new ResourceNotFoundException("Action $action not found in the $service context");
        }

        $method = new \ReflectionMethod($this, $action);
        $authRequirement = ActionAuthResolver::resolve($this, $method, $requestAction, $service);
        ActionAuthResolver::enforce($this, $authRequirement);

        $validationRules = ActionValidationResolver::resolve($method);
        if ($validationRules !== []) {
            rules($data, $validationRules);
        }

        // load it as a macro or a normal action method
        $response = $this->$action($data, $files, $this->request);

        if (is_a($response, ApiResponse::class)){
            return $response;
        }

        throw new Exception("$action did not return a correct ApiResponse object. Did your return `response()`?");
    }
}
