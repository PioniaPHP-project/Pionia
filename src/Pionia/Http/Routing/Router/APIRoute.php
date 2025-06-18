<?php

namespace Pionia\Http\Routing;
use Pionia\Http\Routing\Guards\RouteGuardInterface;
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

    function allowGet(bool $bool = true): static {
        if ($bool){
            $this->methods = array_filter($this->methods, fn ($method) => $method !== 'GET');
        }
        return $this;
    }

    function noGet(): static
    {
        return $this->allowGet(false);
    }

    function allowPost(bool $bool = true): static
    {
        if ($bool){
            $this->methods = array_filter($this->methods, fn ($method) => $method !== 'POST');
        }
        return $this;
    }

    function noPost(): static
    {
        return $this->allowPost(false);
    }

    function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    function version(string $version): static
    {
        $this->version = $version;
        return $this;
    }

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

        return new Route($path);
    }
}
