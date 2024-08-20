<?php

namespace Pionia\Pionia\Http\Base;

use DI\Container;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Base\Utils\Microable;
use Pionia\Pionia\Contracts\KernelContract;
use Pionia\Pionia\Cors\PioniaCors;
use Pionia\Pionia\Http\Request\Request;

class WebKernel implements KernelContract
{
    use Microable;

    private PioniaApplication $app;

    private PioniaCors $corsWorker;


    public function __construct(PioniaApplication $application)
    {
        $this->app = $application;

        $this->corsWorker = new PioniaCors($application);
    }

    public function handle(Request $request, array $routes){
    }

    /**
     * This will run until any of the backends successfully authenticates the user
     *
     * or until all the backends are complete
     * @param Request $request
     * @return Request
     */
    private function authenticationBackendWorker(Request $request): Request
    {
        if ($request->isAuthenticated() || ($request->getAuth() && $request->getAuth()->user)) {
            return $request;
        }

        $backends = $this->app->env->get('authentications');

        $current = array_shift($backends);

        $klass = new $current();

        $userObject =  $klass->authenticate($request);

        // if there is an instance, we set it to context and the next  iteration will be terminated immediately
        if ($userObject){
            $request->setAuthenticationContext($userObject);
            return $request;
        }

        // if we still have more, we call the next
        if (count($backends) > 0) {
            return $this->authenticationBackendWorker($request, $backends);
        }

        // otherwise we abort the process and return the request and proceed. It will be handled on the action or service
        // level
        return $request;
    }


    public function container(): Container
    {
        return $this->app->context;
    }
}
