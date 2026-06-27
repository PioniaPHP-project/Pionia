<?php

namespace Pionia\Http\Request;

use Pionia\Auth\ContextUserObject;
use Pionia\Collections\Arrayable;
use Pionia\Http\Bag\FileBag;
use Pionia\Http\Bag\HeaderBag;
use Pionia\Http\Bag\ParameterBag;
use Pionia\Http\UploadedFile;
use Pionia\Utils\Microable;

/**
 * Native HTTP request object for Pionia (no Symfony dependency).
 *
 * @property bool $authenticated Whether the request is authenticated or not
 * @property ContextUserObject|null $auth The currently logged user in context object
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class Request
{
    use Microable;

    public readonly HeaderBag $headers;

    public readonly ParameterBag $query;

    public readonly ParameterBag $request;

    public readonly ParameterBag $attributes;

    public readonly ParameterBag $cookies;

    public readonly FileBag $files;

    private bool $authenticated = false;

    private ContextUserObject | null $auth = null;

    private ?Arrayable $dataCache = null;

    private ?ParameterBag $payloadCache = null;

    public function __construct(
        private readonly array $server = [],
        private readonly string $content = '',
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        ?HeaderBag $headers = null,
    ) {
        $this->query = new ParameterBag($query);
        $this->request = new ParameterBag($request);
        $this->attributes = new ParameterBag($attributes);
        $this->cookies = new ParameterBag($cookies);
        $this->files = new FileBag($files);
        $this->headers = $headers ?? self::headersFromServer($server);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public static function create(
        string $uri,
        string $method = 'GET',
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null,
    ): static {
        $parts = parse_url($uri) ?: [];
        $path = $parts['path'] ?? '/';
        $queryString = $parts['query'] ?? '';

        $server = array_replace([
            'SERVER_NAME' => 'localhost',
            'HTTP_HOST' => 'localhost',
            'SERVER_PORT' => 80,
            'REQUEST_URI' => $uri,
            'REQUEST_METHOD' => strtoupper($method),
            'SCRIPT_NAME' => '',
            'QUERY_STRING' => $queryString,
        ], $server);

        $server['REQUEST_METHOD'] = strtoupper($method);
        $server['REQUEST_URI'] = $uri;
        $server['QUERY_STRING'] = $queryString;

        $query = $parameters;
        if ($queryString !== '' && $query === []) {
            parse_str($queryString, $query);
        }

        $requestParams = in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && ($content === null || $content === '')
            ? $parameters
            : [];

        if ($requestParams !== [] && $query === $parameters) {
            $query = [];
        }

        return new static(
            $server,
            $content ?? '',
            $query,
            $requestParams,
            [],
            $cookies,
            $files,
        );
    }

    public static function createFromGlobals(): static
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $content = file_get_contents('php://input') ?: '';

        return new static(
            $_SERVER,
            $content,
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
        );
    }

    public function getAuth(): ?ContextUserObject
    {
        return $this->auth;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated || ($this->auth && $this->auth->authenticated);
    }

    public function setAuthenticationContext(ContextUserObject $userObject): static
    {
        if (!empty($userObject->user)) {
            $userObject->authenticated = true;
            $this->authenticated = true;
        }
        $this->auth = $userObject;

        return $this;
    }

    public function clearAuthentication(): static
    {
        $this->authenticated = false;
        $this->auth = null;

        return $this;
    }

    public function getData(): Arrayable
    {
        if ($this->dataCache !== null) {
            return $this->dataCache;
        }

        return $this->dataCache = arr($this->cookies->all())
            ->merge($this->query->all())
            ->merge($this->files->all())
            ->merge($this->getPayload()->all());
    }

    public function getFileByName(string $fileName): ?UploadedFile
    {
        if ($this->getContentTypeFormat() === 'form') {
            $file = $this->files->get($fileName);

            return $file instanceof UploadedFile ? $file : null;
        }

        return null;
    }

    public function getMethod(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    public function getPathInfo(): string
    {
        $requestUri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($requestUri, PHP_URL_PATH);

        return $path === '' || $path === null ? '/' : $path;
    }

    public function getRequestUri(): string
    {
        return (string) ($this->server['REQUEST_URI'] ?? $this->getPathInfo());
    }

    public function getScheme(): string
    {
        if (($this->server['HTTPS'] ?? '') === 'on' || ($this->server['HTTPS'] ?? '') === '1') {
            return 'https';
        }

        if (($this->server['SERVER_PORT'] ?? null) === '443') {
            return 'https';
        }

        return 'http';
    }

    public function getPort(): int
    {
        if (isset($this->server['SERVER_PORT'])) {
            return (int) $this->server['SERVER_PORT'];
        }

        return $this->getScheme() === 'https' ? 443 : 80;
    }

    public function getHost(): string
    {
        if (isset($this->server['HTTP_HOST'])) {
            $host = (string) $this->server['HTTP_HOST'];

            return explode(':', $host)[0];
        }

        return (string) ($this->server['SERVER_NAME'] ?? 'localhost');
    }

    public function getClientIp(): ?string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($this->server[$key])) {
                $value = (string) $this->server[$key];

                return trim(explode(',', $value)[0]);
            }
        }

        return null;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentTypeFormat(): ?string
    {
        $contentType = (string) $this->headers->get('Content-Type', '');

        return match (true) {
            str_contains($contentType, 'json') => 'json',
            str_contains($contentType, 'form-urlencoded') || str_contains($contentType, 'multipart') => 'form',
            default => null,
        };
    }

    public function getPayload(): ParameterBag
    {
        if ($this->payloadCache !== null) {
            return $this->payloadCache;
        }

        if ($this->getContentTypeFormat() === 'json') {
            $decoded = json_decode($this->getContent(), true);

            return $this->payloadCache = new ParameterBag(is_array($decoded) ? $decoded : []);
        }

        return $this->payloadCache = new ParameterBag($this->request->all());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->query->has($key)) {
            return $this->query->get($key);
        }

        if ($this->request->has($key)) {
            return $this->request->get($key);
        }

        return $this->getPayload()->get($key, $default);
    }

  /**
     * @param array<string, mixed> $server
     */
    private static function headersFromServer(array $server): HeaderBag
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$name] = [(string) $value];
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['Content-Type'] = [(string) $server['CONTENT_TYPE']];
        }

        if (isset($server['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = [(string) $server['CONTENT_LENGTH']];
        }

        return new HeaderBag($headers);
    }
}
