<?php

namespace Pionia\Http\Routing\Guards;

use Pionia\Collections\Arrayable;

/**
 * Adds guards for services in the same switch
 * @final
 */
class ServiceGuard implements RouteGuardInterface
{
    /**
     * The list of rules to add
     * @var array
     */
    private array $rules = [];

    /**
     * The list of services to target
     * @var array
     */
    private array $services = [];

    /**
     * Used to initialize rules for one service at a time.
     * @param string $serviceName the name of the target that was used while registering the service
     * @return static
     *@see ServiceGuard::services() if you intend to target multiple services in the same switch
     */
    static function service(string $serviceName): static
    {
        $instance = new static();
        $instance->services[] = $serviceName;
        return $instance;
    }

    /**
     * Adds multiple services under the same switch onto which the next-defined rules shall apply
     * @param array $serviceNames Names of the services used while registering them.
     * @return static
     */
    static function services(array $serviceNames): static {
        $instance = new static();
        $instance->services = array_merge($serviceNames, $instance->services);
        return $instance;
    }

    /**
     * Ensures only authenticated users can pass
     * @return $this
     */
    function authenticated(): static
    {
        $this->rules['authenticated'] = true;
        return $this;
    }

    /**
     * Only authenticated users with the permissions/authorities identified here shall access the service/services
     * @param array $perms
     * @return $this
     */
    function perms(array $perms): static
    {
        $this->rules['perms'] = $perms;
        return $this;
    }

    /**
     * Service will be marked as in offline
     * @return $this
     */
    function offline(): static
    {
        $this->rules['offline'] = true;
        return $this;
    }

    /**
     * Service will be marked as in maintenance mode with a clear error identifying the same
     * @return $this
     */
    function inMaintenanceMode(): static
    {
        $this->rules['maintenance'] = true;
        return $this;
    }

    /**
     * Only post requests will be allowed for this service
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
        return arr(['rules' => $this->rules, 'services' => $this->services, 'actions' => null]);
    }
}
