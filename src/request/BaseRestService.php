<?php

namespace jetPhp\request;

use jetPhp\exceptions\ResourceNotFoundException;
use jetPhp\response\BaseResponse;

/**
 * This is the main class all other services must extend.
 * It contains the basic methods that all services will need for authentication and request processing
 *
 * @author Jet - ezrajet9@gmail.com
 * @property Request $request The request object
 * @property array $deactivatedActions An array of actions that are deactivated for the current service
 * @property array $actionsRequiringAuth An array of actions that require authentication
 * @property bool $serviceRequiresAuth If true, the entire service requires authentication
 * @property string | null $authMessage This message will be displayed when the entire service requires authentication
 *
 */
abstract class BaseRestService
{

    use AuthTrait;
    use RequestActionTrait;

    /**
     * @var Request $request The request object
     */
    public Request $request;

    /**
     * @var array $deactivatedActions An array of actions that are deactivated for the current service
     */
    public array $deactivatedActions = [];

    /**
     * @var array This array contains the actions that require authentication
     */
    public array $actionsRequiringAuth = [];

    /**
     * @var bool If true, the entire service requires authentication
     */
    public bool  $serviceRequiresAuth = false;

    /**
     * @var string | null This message will be displayed when the entire service requires authentication
     */
    public ?string $authMessage = null;

    /**
     * @throws ResourceNotFoundException
     */
    public function processAction(string $action, Request $request): BaseResponse
    {
        $service = $request->getData()['SERVICE'];

        if ($this->serviceRequiresAuth && !$request->isAuthenticated()) {
            throw new ResourceNotFoundException($this->authMessage??"Service $service requires authentication");
        }

        $this->request = $request;


        if (in_array($action, $this->deactivatedActions)) {
            throw new ResourceNotFoundException("Action $action is currently deactivated for this service");
        }

        if (in_array($action, $this->actionsRequiringAuth) && !$request->isAuthenticated()) {
            throw new ResourceNotFoundException("Action $action requires authentication");
        }

        $data = $this->request->getData();
        $files = $this->request->files;
        // here we attempt to call the action method on the current class
        if (method_exists($this, $action)) {
            return $this->$action($data, $files);
        }
        throw new ResourceNotFoundException("Action $action not found in the $service context");
    }
}
