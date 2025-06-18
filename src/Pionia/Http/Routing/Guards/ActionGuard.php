<?php

namespace Pionia\Http\Routing\Guards;

use Pionia\Collections\Arrayable;

/**
 * Adds guards for service actions in the same service
 * @final
 */
class ActionGuard implements RouteGuardInterface
{
    /**
     * The list of rules to add
     * @var array
     */
    private array $rules = [];

    /**
     * The list of actions to target
     * @var array
     */
    private array $actions = [];

    static function serviceAction(string $service, string $action): static
    {
        $instance = new static();
        $instance->actions[$service]= $action;
        return $instance;
    }

    static function serviceActions(string $service, array $actions): static
    {
        $instance = new static();
        $instance->actions[$service]= $actions;
        return $instance;
    }

    /**
     * Ensures only authenticated users can access the defined actions or actions
     * @return $this
     */
    function authenticated(): static
    {
        $this->rules['authenticated'] = true;
        return $this;
    }

    /**
     * Only authenticated users with the permissions/authorities identified here shall access the actions defined
     * @param array $perms
     * @return $this
     */
    function perms(array $perms): static
    {
        $this->rules['perms'] = $perms;
        return $this;
    }

    /**
     * Service Action[s] will be marked as in offline
     * @return $this
     */
    function offline(): static
    {
        $this->rules['offline'] = true;
        return $this;
    }

    /**
     * Service action[s] will be marked as in maintenance mode with a clear error identifying the same
     * @return $this
     */
    function inMaintenanceMode(): static
    {
        $this->rules['maintenance'] = true;
        return $this;
    }

    /**
     * Only post-requests will be allowed for this action
     * @return $this
     */
    function postOnly(): static
    {
        $this->rules['post_only'] = true;
        return $this;
    }

    /**
     * Only GET will be allowed for this action
     * @return $this
     */
    function getOnly(): static
    {
        $this->rules['get_only'] = true;
        return $this;
    }

    function get(): Arrayable
    {
        return arr(['rules' => $this->rules, 'services' => null, 'actions' => $this->actions]);
    }
}
