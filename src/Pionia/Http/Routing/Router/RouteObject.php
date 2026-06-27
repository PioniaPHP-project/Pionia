<?php

namespace Pionia\Http\Routing\Router;

use Pionia\Http\Routing\RouteDefinition;

class RouteObject
{
    private ?string $path = null;
    private array $options = [];
    private string $host  = '';
    private array $schemas   = ["http", "https"];
    private string  $conditions  = '';

    private array $methods = [];
    private array $requirements = [];
    private array $controller = [];

    public static function post($path): static
    {
        $route = new static();

        $route->path = $path;
        $route->addMethod('POST');
        return $route;
    }

    public static function get($path): static
    {
        $route = new static();

        $route->path = $path;
        $route->addMethod('GET');
        return $route;
    }

    public function controller(array $controller): static
    {
        $this->controller = $controller;
        return $this;
    }

    public function requires(array $requirements): static
    {
        $this->requirements = array_merge($this->requirements, $requirements);
        return $this;
    }

    public function host($host): static
    {
        $this->host = $host;
        return $this;
    }

    public function addSchema($schema): static
    {
        if (!in_array($schema, $this->schemas)) {
            $this->schemas[]=$schema;
        }
        return $this;
    }

    public function options(?array $options = []): static
    {
        $this->options = array_merge($this->options, $options ?? []);
        return $this;
    }

    public function conditions(string $conditions): static
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function addMethod($method): static
    {
        if (!in_array($method, ['GET', 'POST'])) {
            throw new \InvalidArgumentException('Invalid HTTP method for '.$this->path);
        }

        if (!in_array($method, $this->methods)){
            $this->methods[] = strtoupper(trim($method));
        }

        return $this;
    }

    public function build(): RouteDefinition
    {
        $requirements = $this->requirements;

        foreach ($this->options as $key => $value) {
            if (is_string($key) && str_contains((string) $this->path, '{' . $key . '}')) {
                $requirements[$key] = (string) $value;
            }
        }

        return new RouteDefinition(
            (string) $this->path,
            $this->controller,
            $requirements,
            $this->methods,
        );
    }


}
