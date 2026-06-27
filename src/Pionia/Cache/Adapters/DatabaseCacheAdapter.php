<?php

namespace Pionia\Cache\Adapters;

use PDO;
use Pionia\Cache\Concerns\ImplementsBulkCacheOperations;
use Pionia\Cache\Concerns\ValidatesCacheKeys;
use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Cache\Contracts\PrunableCacheAdapterInterface;
use Pionia\Cache\InvalidCacheArgumentException;
use Pionia\Cache\Support\CacheTtl;
use Pionia\Porm\ConnectionManager;

/**
 * PDO-backed PSR-16 store (shared across workers when using a real database).
 */
final class DatabaseCacheAdapter implements CacheAdapterInterface, PrunableCacheAdapterInterface
{
    use ValidatesCacheKeys;
    use ImplementsBulkCacheOperations;

    private readonly PDO $pdo;

    public function __construct(
        ConnectionManager $connections,
        private readonly string $table = 'cache',
        private readonly int $defaultTtl = 0,
        null|string $connection = 'default',
    ) {
        $pdo = $connections->connection($connection)->getPdo();
        if (!$pdo instanceof PDO) {
            throw new InvalidCacheArgumentException('Database cache requires an active PDO connection.');
        }

        $this->pdo = $pdo;
        $this->ensureTable();
    }

    public function get($key, $default = null): mixed
    {
        $this->assertValidKey($key);
        $statement = $this->pdo->prepare(
            sprintf('SELECT value, expiration FROM %s WHERE cache_key = :key LIMIT 1', $this->tableName()),
        );
        $statement->execute(['key' => (string) $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return $default;
        }

        $expiration = isset($row['expiration']) ? (int) $row['expiration'] : null;
        if (CacheTtl::isExpired($expiration)) {
            $this->delete($key);

            return $default;
        }

        try {
            $value = unserialize((string) $row['value'], ['allowed_classes' => true]);
        } catch (\Throwable) {
            $this->delete($key);

            return $default;
        }

        return $value;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);
        $expiration = CacheTtl::expiresAt($ttl, $this->defaultTtl);
        $payload = serialize($value);
        $statement = $this->pdo->prepare($this->upsertSql());

        return $statement->execute([
            'key' => (string) $key,
            'value' => $payload,
            'expiration' => $expiration,
        ]);
    }

    public function delete($key): bool
    {
        $this->assertValidKey($key);
        $statement = $this->pdo->prepare(
            sprintf('DELETE FROM %s WHERE cache_key = :key', $this->tableName()),
        );

        return $statement->execute(['key' => (string) $key]);
    }

    public function clear(): bool
    {
        return $this->pdo->exec(sprintf('DELETE FROM %s', $this->tableName())) !== false;
    }

    public function has($key): bool
    {
        $this->assertValidKey($key);
        $statement = $this->pdo->prepare(
            sprintf('SELECT expiration FROM %s WHERE cache_key = :key LIMIT 1', $this->tableName()),
        );
        $statement->execute(['key' => (string) $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        $expiration = isset($row['expiration']) ? (int) $row['expiration'] : null;
        if (CacheTtl::isExpired($expiration)) {
            $this->delete($key);

            return false;
        }

        return true;
    }

    public function prune(): bool
    {
        $statement = $this->pdo->prepare(
            sprintf(
                'DELETE FROM %s WHERE expiration IS NOT NULL AND expiration <= :now',
                $this->tableName(),
            ),
        );
        $statement->execute(['now' => time()]);

        return $statement->rowCount() > 0;
    }

    private function ensureTable(): void
    {
        $table = $this->tableName();
        $this->pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                cache_key VARCHAR(255) PRIMARY KEY,
                value TEXT NOT NULL,
                expiration INTEGER
            )',
            $table,
        ));
    }

    private function tableName(): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $this->table) ?: 'cache';
    }

    private function driverName(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function upsertSql(): string
    {
        $table = $this->tableName();

        return match ($this->driverName()) {
            'pgsql' => sprintf(
                'INSERT INTO %s (cache_key, value, expiration) VALUES (:key, :value, :expiration)
                 ON CONFLICT (cache_key) DO UPDATE SET value = EXCLUDED.value, expiration = EXCLUDED.expiration',
                $table,
            ),
            'sqlite' => sprintf(
                'INSERT INTO %s (cache_key, value, expiration) VALUES (:key, :value, :expiration)
                 ON CONFLICT(cache_key) DO UPDATE SET value = excluded.value, expiration = excluded.expiration',
                $table,
            ),
            default => sprintf(
                'REPLACE INTO %s (cache_key, value, expiration) VALUES (:key, :value, :expiration)',
                $table,
            ),
        };
    }
}
