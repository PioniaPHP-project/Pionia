<?php

namespace Pionia\Database\Migrations;

use PDO;
use Pionia\Base\Provider\Provider;
use Pionia\Database\Schema;
use Pionia\Porm\Driver\Connection;
use Throwable;

/**
 * Discovers, runs, rolls back, and reports migration status.
 *
 * Paths: app (`database/migrations` or `[migrations] PATH`) plus each provider's `migrations()`.
 */
final class Migrator
{
    private MigrationRepository $repository;

    private string $connectionName;

    /** @var list<array{path: string, source: string}> */
    private array $paths = [];

    public function __construct(
        ?string $connectionName = null,
        ?string $repositoryTable = null,
    ) {
        $this->connectionName = $connectionName ?? 'default';
        $table = $repositoryTable ?? $this->configValue('TABLE', 'migrations');
        $this->repository = new MigrationRepository($this->connection(), $table);
    }

    public function getRepository(): MigrationRepository
    {
        return $this->repository;
    }

    public function connection(): Connection
    {
        return connectionManager()->connection($this->connectionName);
    }

    /**
     * @param list<string>|string $paths
     * @param string $source Label for status output (e.g. "app", provider class)
     */
    public function path(array|string $paths, string $source = 'app'): self
    {
        foreach ((array) $paths as $path) {
            if ($path === '' || !is_dir($path)) {
                continue;
            }
            $this->paths[] = ['path' => rtrim($path, DIRECTORY_SEPARATOR), 'source' => $source];
        }

        return $this;
    }

    /**
     * Resolve default app + provider migration paths and register them.
     */
    public function discoverDefaultPaths(?string $appPath = null): self
    {
        $appPath ??= $this->resolveAppMigrationsPath();
        if ($appPath !== null) {
            if (!is_dir($appPath)) {
                @mkdir($appPath, 0775, true);
            }
            $this->path($appPath, 'app');
        }

        foreach ($this->providerMigrationPaths() as $entry) {
            $this->path($entry['path'], $entry['source']);
        }

        return $this;
    }

    /**
     * Run all pending migrations in a single new batch.
     *
     * @return list<string> Basenames that were run
     */
    public function migrate(): array
    {
        $this->repository->createRepository();
        $pending = $this->pendingMigrations();
        if ($pending === []) {
            return [];
        }

        $batch = $this->repository->getNextBatchNumber();
        $ran = [];

        foreach ($pending as $migration) {
            $this->runUp($migration, $batch);
            $ran[] = $migration['name'];
        }

        return $ran;
    }

    /**
     * Roll back the last `$steps` batches.
     *
     * @return list<string>
     */
    public function rollback(int $steps = 1): array
    {
        $this->repository->createRepository();
        $rolled = [];

        for ($i = 0; $i < max(1, $steps); $i++) {
            $batch = $this->repository->getLastBatchNumber();
            if ($batch < 1) {
                break;
            }
            foreach ($this->repository->getMigrationsByBatch($batch) as $row) {
                $file = $this->findMigrationFile($row['migration']);
                if ($file === null) {
                    throw new MigrationException("Cannot roll back [{$row['migration']}]: file not found.");
                }
                $this->runDown($file);
                $rolled[] = $row['migration'];
            }
        }

        return $rolled;
    }

    /**
     * @return list<array{name: string, batch: int|null, status: string, source: string, path: string}>
     */
    public function status(): array
    {
        $this->repository->createRepository();
        $ran = [];
        foreach ($this->repository->getMigrationRows() as $row) {
            $ran[$row['migration']] = $row;
        }

        $rows = [];
        foreach ($this->allMigrationFiles() as $file) {
            $name = $file['name'];
            $rows[] = [
                'name' => $name,
                'batch' => isset($ran[$name]) ? (int) $ran[$name]['batch'] : null,
                'status' => isset($ran[$name]) ? 'Ran' : 'Pending',
                'source' => $file['source'],
                'path' => $file['path'],
            ];
            unset($ran[$name]);
        }

        // Orphan records (file missing)
        foreach ($ran as $name => $row) {
            $rows[] = [
                'name' => $name,
                'batch' => (int) $row['batch'],
                'status' => 'Missing',
                'source' => '?',
                'path' => '',
            ];
        }

        return $rows;
    }

    /**
     * Drop application tables (sqlite: all user tables except migrations) then re-migrate.
     *
     * @return list<string>
     */
    public function fresh(bool $force = false): array
    {
        if (!$force && !$this->isSqlite()) {
            // Non-interactive safety: require --force outside sqlite in-memory/tests
            throw new MigrationException('migrate:fresh requires --force on non-sqlite databases.');
        }

        Schema::connection($this->connectionName);

        if ($this->isSqlite()) {
            $this->dropAllSqliteTables();
        } else {
            // Reverse all batches then drop repository
            while ($this->repository->getLastBatchNumber() > 0) {
                $this->rollback(1);
            }
            $this->dropAllTablesExceptMigrations();
            $this->repository->deleteRepository();
        }

        $this->repository = new MigrationRepository($this->connection(), $this->repository->getTable());
        $this->repository->createRepository();

        return $this->migrate();
    }

    /**
     * @return list<array{name: string, path: string, source: string}>
     */
    public function pendingMigrations(): array
    {
        $ran = array_flip($this->repository->getRan());
        $pending = [];
        foreach ($this->allMigrationFiles() as $file) {
            if (!isset($ran[$file['name']])) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    /**
     * @return list<array{name: string, path: string, source: string}>
     */
    public function allMigrationFiles(): array
    {
        /** @var array<string, array{name: string, path: string, source: string}> $byName */
        $byName = [];

        foreach ($this->paths as $entry) {
            $files = glob($entry['path'] . DIRECTORY_SEPARATOR . '*.php') ?: [];
            foreach ($files as $file) {
                $name = basename($file, '.php');
                if (isset($byName[$name])) {
                    throw new MigrationException(
                        "Duplicate migration basename [{$name}] in {$byName[$name]['path']} and {$file}.",
                    );
                }
                $byName[$name] = [
                    'name' => $name,
                    'path' => $file,
                    'source' => $entry['source'],
                ];
            }
        }

        ksort($byName, SORT_STRING);

        return array_values($byName);
    }

    /**
     * @param array{name: string, path: string, source: string} $migration
     */
    private function runUp(array $migration, int $batch): void
    {
        $instance = $this->resolve($migration['path']);
        $connection = $instance->getConnection() ?? $this->connectionName;
        Schema::connection($connection);

        $this->withinTransactionIfSupported(function () use ($instance, $migration, $batch): void {
            $instance->up();
            $this->repository->log($migration['name'], $batch);
        });
    }

    /**
     * @param array{name: string, path: string, source: string} $file
     */
    private function runDown(array $file): void
    {
        $instance = $this->resolve($file['path']);
        $connection = $instance->getConnection() ?? $this->connectionName;
        Schema::connection($connection);

        $this->withinTransactionIfSupported(function () use ($instance, $file): void {
            $instance->down();
            $this->repository->delete($file['name']);
        });
    }

    private function resolve(string $path): Migration
    {
        $migration = require $path;
        if (!$migration instanceof Migration) {
            throw new MigrationException("Migration file [{$path}] must return an instance of " . Migration::class . '.');
        }

        return $migration;
    }

    /**
     * @return array{name: string, path: string, source: string}|null
     */
    private function findMigrationFile(string $name): ?array
    {
        foreach ($this->allMigrationFiles() as $file) {
            if ($file['name'] === $name) {
                return $file;
            }
        }

        return null;
    }

    private function withinTransactionIfSupported(callable $callback): void
    {
        $pdo = $this->connection()->getPdo();
        if (!$pdo instanceof PDO) {
            throw new MigrationException('Database connection has no PDO instance.');
        }

        $driver = $this->connection()->getType();
        $useTx = in_array($driver, ['sqlite', 'pgsql', 'postgres', 'postgresql'], true);

        if (!$useTx) {
            $callback();

            return;
        }

        $pdo->beginTransaction();
        try {
            $callback();
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function isSqlite(): bool
    {
        return $this->connection()->getType() === 'sqlite';
    }

    private function dropAllSqliteTables(): void
    {
        $pdo = $this->connection()->getPdo();
        if (!$pdo instanceof PDO) {
            return;
        }

        $pdo->exec('PRAGMA foreign_keys = OFF');
        $stmt = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
        );
        $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS "' . str_replace('"', '""', (string) $table) . '"');
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    private function dropAllTablesExceptMigrations(): void
    {
        $driver = $this->connection()->getType();
        $pdo = $this->connection()->getPdo();
        if (!$pdo instanceof PDO) {
            return;
        }

        $migrationsTable = $this->repository->getTable();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $stmt = $pdo->query('SHOW TABLES');
            $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($tables as $table) {
                if ($table === $migrationsTable) {
                    continue;
                }
                $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $table) . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            return;
        }

        if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            $stmt = $pdo->query(
                "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = current_schema()",
            );
            $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($tables as $table) {
                if ($table === $migrationsTable) {
                    continue;
                }
                $pdo->exec('DROP TABLE IF EXISTS "' . str_replace('"', '""', (string) $table) . '" CASCADE');
            }
        }
    }

    private function resolveAppMigrationsPath(): ?string
    {
        $configured = $this->configValue('PATH');
        if (is_string($configured) && $configured !== '') {
            if ($configured[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $configured)) {
                return $configured;
            }
            $base = defined('BASE_PATH') ? (string) BASE_PATH : getcwd();

            return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($configured, DIRECTORY_SEPARATOR);
        }

        try {
            return directoryPath(\DIRECTORIES::MIGRATIONS_DIR->name);
        } catch (Throwable) {
            $base = defined('BASE_PATH') ? (string) BASE_PATH : getcwd();

            return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        }
    }

    /**
     * @return list<array{path: string, source: string}>
     */
    private function providerMigrationPaths(): array
    {
        $entries = [];
        $providers = [];

        try {
            $fromEnv = env('app_providers', []);
            if (is_array($fromEnv)) {
                $providers = array_values($fromEnv);
            }
        } catch (Throwable) {
        }

        try {
            $cached = realm()->getSilently('app_providers');
            if ($cached instanceof \Pionia\Collections\Arrayable) {
                $providers = array_values(array_unique(array_merge($providers, $cached->all())));
            } elseif (is_array($cached)) {
                $providers = array_values(array_unique(array_merge($providers, $cached)));
            }
        } catch (Throwable) {
        }

        foreach ($providers as $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }
            try {
                $provider = app()->make($class);
            } catch (Throwable) {
                try {
                    $provider = new $class(app());
                } catch (Throwable) {
                    continue;
                }
            }

            if (!$provider instanceof Provider) {
                continue;
            }

            foreach ($provider->migrations() as $path) {
                if (is_string($path) && $path !== '') {
                    $entries[] = ['path' => $path, 'source' => $class];
                }
            }
        }

        return $entries;
    }

    private function configValue(string $key, mixed $default = null): mixed
    {
        try {
            $section = env('migrations', []);
            if (is_array($section) && array_key_exists($key, $section)) {
                return $section[$key];
            }
            // EnvResolver may flatten section keys
            $flat = env('migrations.' . $key, null);
            if ($flat !== null) {
                return $flat;
            }
        } catch (Throwable) {
        }

        return $default;
    }
}
