<?php

namespace Pionia\Auth;

use Pionia\Auth\Events\PostAuthRunEvent;
use Pionia\Auth\Events\PreAuthRunEvent;
use Pionia\Collections\Arrayable;
use Pionia\Contracts\AuthenticationChainContract;
use Pionia\Contracts\AuthenticationContract;
use Pionia\Http\Request\Request;
use Pionia\Utils\Microable;
use Pionia\Utils\Support;

/**
 * Authentication chain.
 *
 * This class is responsible for managing the authentication backends in the application.
 *
 * It will be run on every request to authenticate the user by the kernel
 */
class AuthenticationChain implements AuthenticationChainContract
{
    use Microable;

    private Arrayable $authentications;

    public function __construct()
    {
        $this->authentications = app()->getOrDefault(app()::AUTHENTICATIONS_TAG, new Arrayable([]));
    }
    /*
     * Checks if a string rep is actually an AuthenticationContract
     */
    public function isAuthenticationContract(string $authenticationContract): bool
    {
        return Support::extends($authenticationContract, AuthenticationBackend::class) || Support::implements($authenticationContract, AuthenticationContract::class);
    }

    public function addAuthenticationBackend(string $authenticationContract): static
    {
        if (!$this->isAuthenticationContract($authenticationContract)) {
            throw new \InvalidArgumentException("The $authenticationContract authentication must extend " . AuthenticationBackend::class);
        }
        $this->authentications->add($authenticationContract);
        return $this->updateAuthenticationsInContext();
    }

    /**
     * Returns a list of all registered authentications
     * @return array
     */
    public function getAuthentications(): array
    {
        return $this->authentications->all();
    }

    /**
     * Adds an authentication before another authentication.
     * Cool for prioritizing certain authentications over others.
     * @param string $authToPoint
     * @param string $authToAdd
     * @return $this
     */
    public function addBefore(string $authToPoint, string $authToAdd): static
    {
        if (!$this->isAuthenticationContract($authToAdd)) {
            throw new \InvalidArgumentException("The authentication contract must implement " . AuthenticationContract::class);
        }

        if (!$this->authentications->has($authToPoint)) {
            throw new \InvalidArgumentException("The authentication contract to add before does not exist in the chain");
        }

        $this->authentications->addBefore($authToPoint, $authToAdd);

        logger()->info("Added authentication before $authToPoint");
        // we need to repopulate the container with the new authentications
        return $this->updateAuthenticationsInContext();
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

        logger()?->info("Added authentication after $authToPoint");
        // we need to repopulate the container with the new authentications
        return $this->updateAuthenticationsInContext();
    }

    private function updateAuthenticationsInContext(): static
    {
        app()->contextArrAdd(app()::AUTHENTICATIONS_TAG, $this->authentications)
            ->cache(app()::AUTHENTICATIONS_TAG, $this->authentications);
        return $this;
    }

    /**
     * Add multiple authentications at ago
     * @param array|Arrayable $authentications
     * @return $this
     */
    public function addAll(array | Arrayable $authentications): static
    {
        $this->authentications->merge($authentications);
        return $this->updateAuthenticationsInContext();
    }

    private function run(Request $request): void
    {
        if ($this->authentications->isEmpty() || $request->isAuthenticated()) {
            return;
        }
        $auth = $this->authentications->shift();
        if (!$auth) {
            return;
        }
        // we create the object first
        $authObj = new $auth($this);

        $service = $request->getData()->get("service");

        if ($authObj instanceof AuthenticationContract) {
            if ($this->canRunOnCurrentService($authObj, $service)) {
                // we run the beforeRun hook
                $authObj->beforeRun($request);
                // we then run the backend
                $this->next($request, $authObj);
            } else {
                logger()?->info("$auth authentication backend skipped on $service");
                $this->run($request);
            }
        }
    }

    /**
     * Run the authentication chain on a request
     * Fires the PreAuthRunEvent before running the chain and PostAuthRunEvent after running the chain
     */
    public function handle(Request $request): void
    {
        event(new PreAuthRunEvent($this), PreAuthRunEvent::name());

        $this->run($request);

        event(new PostAuthRunEvent($this), PostAuthRunEvent::name());
    }

    private function canRunOnCurrentService(AuthenticationBackend $auth, ?string $currentService): bool
    {
        if ($auth->limitServices) {
            $limits = arr($auth->limitServices);
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
        $this->handle($request);
    }
}
