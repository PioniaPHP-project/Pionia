<?php

namespace Pionia\Pionia\Interceptors\Auths;

use Pionia\Core\Interceptions\BaseAuthenticationBackend;
use Pionia\Pionia\Auth\ContextUserObject;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Base\Utils\Containable;
use Pionia\Pionia\Base\Utils\Microable;
use Pionia\Pionia\Contracts\AuthenticationChainContract;
use Pionia\Pionia\Contracts\AuthenticationContract;
use Pionia\Pionia\Http\Request\Request;
use Pionia\Pionia\Utilities\Arrayable;

/**
 * Authentication chain.
 *
 * This class is responsible for managing the authentication backends in the application.
 *
 * It will be run on every request to authenticate the user by the kernel
 */
class AuthenticationChain implements AuthenticationChainContract
{
    use Microable, Containable;

    private Arrayable $authentications;

    private PioniaApplication $application;

    public function __construct(PioniaApplication $application)
    {
        $this->context = $application->context;
        $this->application = $application;
        $this->authentications = $this->getOrDefault('authentications', Arrayable::toArrayable([]));
    }
    /*
     * Checks if a string rep is actually an AuthenticationContract
     */
    public function isAuthenticationContract(string $authenticationContract): bool
    {
         return class_exists($authenticationContract) && in_array(AuthenticationContract::class, class_parents($authenticationContract));
    }

    public function addAuthenticationBackend(string $authenticationContract): static
    {
        if (!$this->isAuthenticationContract($authenticationContract)) {
            throw new \InvalidArgumentException("The authentication contract must implement " . AuthenticationContract::class);
        }
        $this->authentications->add($authenticationContract);
        return $this;
    }

    public function getAuthentications(): array
    {
        return $this->authentications->all();
    }

    public function addBefore(string $authToPoint, string $authToAdd): static
    {
        if (!$this->isAuthenticationContract($authToAdd)) {
            throw new \InvalidArgumentException("The authentication contract must implement " . AuthenticationContract::class);
        }

        if (!$this->authentications->has($authToPoint)) {
            throw new \InvalidArgumentException("The authentication contract to add before does not exist in the chain");
        }

        $this->authentications->addBefore($authToPoint, $authToAdd);

        $this->application->logger?->info("Added authentication before $authToPoint");
        // we need to repopulate the container with the new authentications
        $this->set('authentications', $this->authentications);
        return $this;
    }

    public function addAfter(string $authToPoint, string $authToAdd): static
    {
        if (!$this->isAuthenticationContract($authToAdd)) {
            throw new \InvalidArgumentException("The authentication contract must implement " . AuthenticationContract::class);
        }

        if (!$this->authentications->has($authToPoint)) {
            throw new \InvalidArgumentException("The authentication contract to add after does not exist in the chain");
        }

        $this->authentications->addAfter($authToPoint, $authToAdd);

        $this->application->logger?->info("Added authentication after $authToPoint");
        // we need to repopulate the container with the new authentications
        $this->set('authentications', $this->authentications);
        return $this;
    }

    public function run(Request $request): void
    {
        if ($this->authentications->isEmpty() || $request->isAuthenticated()) {
            return;
        }
        $auth = $this->authentications->shift();
        if (!$auth) {
            return;
        }
        // we create the object first
        $authObj = new $auth($this->context);

        $service = $request->getData()->get("service");

        if ($authObj instanceof AuthenticationContract) {
            if ($this->canRunOnCurrentService($authObj, $service)) {
                // we run the beforeRun hook
                $authObj->beforeRun($request);
                // we then run the backend
                $this->next($request, $authObj);
            } else {
                $this->application->logger?->info("$auth authentication backend skipped on $service");
                $this->run($request);
            }
        }
    }

    private function canRunOnCurrentService(AuthenticationBackend $auth, ?string $currentService): bool
    {
        if ($auth->limitServices) {
            $limits = Arrayable::toArrayable($auth->limitServices);
            if ($limits->isEmpty()){
                return true;
            }
            if ($limits->has($currentService)){
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    public function next(Request $request, AuthenticationContract $next): void
    {
        $auth = $next->authenticate($request);

        if ($auth && is_a($auth, ContextUserObject::class)) {
            $request->setAuthenticationContext($auth);
        }
        $next->afterRun($request);
        // attempt to run the next authentication in the chain
        // if were are not authenticated yet, we shall proceed otherwise we shall stop
        $this->run($request);
    }
}
