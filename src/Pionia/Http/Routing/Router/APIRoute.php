<?php

namespace Pionia\Http\Routing\Router;
use Pionia\Http\Routing\Guards\RouteGuardInterface;
use Pionia\Http\Routing\SupportedHttpMethods;
use Pionia\Realm\RealmContract;
use Symfony\Component\Routing\Route;

class APIRoute implements APIRouteInterface
{
    private string $version = 'v1/';
    private array $methods = [SupportedHttpMethods::POST, SupportedHttpMethods::GET];

    private bool $requiresAuth = false;
    private RouteGuardInterface|null $guard = null;
    private ?string $switch = null;

    private ?string $name = null;

    public function __construct(string $switch, ?string $version = null)
    {
        $this->switch = $switch;
        $this->version = $version;
    }

    /**
     * @param string $switch the target switch
     * @param string|null $version the API version associated with this switch
     * @return static
     */
    static function to(string $switch, ?string $version = null): static
    {
        return new static($switch, $version);
    }

    function onlyGet(?bool $bool = true): static {
        if ($bool){
            $this->methods = array_filter($this->methods, fn ($method) => $method !== 'GET');
        }
        return $this;
    }

    function noGet(): static
    {
        return $this->onlyGet(false);
    }

    function onlyPost(bool $bool = true): static
    {
        if ($bool){
            $this->methods = array_filter($this->methods, fn ($method) => $method !== 'POST');
        }
        return $this;
    }

    /**
     * Only POST requests will be allowed for the entire API related to the target switch and version
     * @return $this
     */
    function noPost(): static
    {
        return $this->onlyPost(false);
    }


    function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the target API version, defaults to v1/, adding an already existing version will raise an exception
     * @param string $version
     * @return $this
     */
    function version(string $version): static
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Add guards to the services under the specified and their actions too
     * @param RouteGuardInterface $guard
     * @return $this
     */
    function withGuard(RouteGuardInterface $guard): static
    {
        $this->guard = $guard;
        return $this;
    }

    private function cleanVersion($base, $version){
        // if it starts with a /, we trim if off

    }

    protected function build(RealmContract $realm): Route
    {
        $base = $realm->getOrDefault('API_BASE', '/api/');

        $path = $this->cleanVersion($base, $this->version);

        return new Route($path, []);
    }
}
