<?php

namespace Pionia\Porm;

use PDO;
use PDOException;
use Pionia\Porm\Driver\Connection;
use Pionia\Realm\AppRealm;

/**
 * Reuses PDO connections per process (FPM worker / RoadRunner).
 */
class ConnectionManager
{
    /** @var array<string, Connection> */
    private array $connections = [];

    /** @var array<string, string|array> */
    private array $configs = [];

    public function __construct(
        private readonly AppRealm $app,
    ) {
    }

    /** @var array<string, bool> */
    private array $registered = [];

    public function connection(null | string | array $name = 'default'): Connection
    {
        $key = $this->resolveKey($name);

        if (!isset($this->connections[$key])) {
            $this->configs[$key] = $name ?? 'default';
            $this->connections[$key] = Connection::open($this->configs[$key]);
        }

        return $this->ping($key);
    }

    public function register(string $name, Connection $connection): void
    {
        $key = $this->resolveKey($name);
        $this->configs[$key] = $name;
        $this->connections[$key] = $connection;
        $this->registered[$key] = true;
    }

    public function disconnect(?string $name = null): void
    {
        if ($name === null) {
            $this->connections = [];
            $this->configs = [];
            $this->registered = [];

            return;
        }

        $key = $this->resolveKey($name);
        unset($this->connections[$key], $this->configs[$key], $this->registered[$key]);
    }

    public function has(string $name): bool
    {
        return isset($this->connections[$this->resolveKey($name)]);
    }

    private function resolveKey(null | string | array $name): string
    {
        if (is_array($name)) {
            return 'cfg:' . md5(json_encode($name, JSON_THROW_ON_ERROR));
        }

        return 'name:' . ($name ?? 'default');
    }

    private function ping(string $key): Connection
    {
        $connection = $this->connections[$key];

        if ($connection->isTestMode()) {
            return $connection;
        }

        $pdo = $connection->getPdo();
        if (!$pdo instanceof PDO) {
            return $connection;
        }

        try {
            $pdo->query('SELECT 1');
        } catch (PDOException $e) {
            if (!empty($this->registered[$key])) {
                throw $e;
            }

            $this->connections[$key] = Connection::open($this->configs[$key]);
        }

        return $this->connections[$key];
    }
}
