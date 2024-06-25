<?php

namespace Pionia\Request;

use Pionia\Exceptions\ResourceNotFoundException;
use Pionia\Exceptions\UserUnauthenticatedException;
use Pionia\Response\BaseResponse;
use ReflectionException;
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
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 **/

abstract class BaseRestService
{
    /**
     * @var Request $request The request object
     */
    public Request $request;

    use AuthTrait;
    use RequestActionTrait;
    use ValidationTrait;

    /**
     * @var array $deactivatedActions An array of actions that are deactivated for the current service
     */
    public array $deactivatedActions = [];

    /**
     * @var array $actionsRequiringAuth This array contains the actions that require authentication
     */
    public array $actionsRequiringAuth = [];

    /**
     * @var bool $serviceRequiresAuth If true, the entire service requires authentication
     */
    public bool  $serviceRequiresAuth = false;

    /**
     * @var ?string $authMessage This message will be displayed when the entire service requires authentication
     */
    public ?string $authMessage = null;
    /**
     * This method is called when the service is called with an action
     *
     * @param string $action The action to be called
     * @param Request $request The request object
     * @return BaseResponse The response object
     * @throws ResourceNotFoundException|ReflectionException
     * @throws UserUnauthenticatedException
     */
    public function processAction(string $action, Request $request): BaseResponse
    {
        $this->request = $request;

        $service = $request->getData()['SERVICE'];

        if ($this->serviceRequiresAuth) {
            $this->mustAuthenticate($this->authMessage??"Service $service requires authentication");
        }


        if (in_array($action, $this->deactivatedActions)) {
            throw new ResourceNotFoundException("Action $action is currently deactivated for this service");
        }

        if (in_array($action, $this->actionsRequiringAuth)) {
            $this->mustAuthenticate("Action $action requires authentication");
        }

        $data = $this->request->getData();
        $files = $this->request->files;
        // here we attempt to call the action method on the current class
        if (method_exists($this, $action)) {
            $reflection = new ReflectionMethod($this, $action);
            $reflection->setAccessible(true);
            return $reflection->invoke($this, $data, $files, $request);
        }
        throw new ResourceNotFoundException("Action $action not found in the $service context");
    }
}
