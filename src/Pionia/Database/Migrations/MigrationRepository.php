<?php

namespace Pionia\Database\Migrations;

use PDO;
use Pionia\Porm\Driver\Connection;

/**
 * Persists applied migration records in a configurable `migrations` table.
 */
final class MigrationRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = 'migrations',
    ) {
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function repositoryExists(): bool
    {
        $pdo = $this->pdo();
        $driver = $this->connection->getType();

        if ($driver === 'sqlite') {
            $stmt = $pdo->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $this->quote($this->table),
            );

            return $stmt !== false && (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?',
            );
            $stmt->execute([$this->table]);

            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        );
        $stmt->execute([$this->table]);

        return (bool) $stmt->fetchColumn();
    }

    public function createRepository(): void
    {
        if ($this->repositoryExists()) {
            return;
        }

        $driver = $this->connection->getType();
        $table = $this->wrap($this->table);

        $sql = match (true) {
            $driver === 'sqlite' => <<<SQL
                CREATE TABLE {$table} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INTEGER NOT NULL,
                    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL,
            in_array($driver, ['pgsql', 'postgres', 'postgresql'], true) => <<<SQL
                CREATE TABLE {$table} (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INTEGER NOT NULL,
                    applied_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL,
            default => <<<SQL
                CREATE TABLE {$table} (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY migrations_migration_unique (migration)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                SQL,
        };

        $this->pdo()->exec($sql);
    }

    /**
     * @return list<string>
     */
    public function getRan(): array
    {
        $this->createRepository();
        $stmt = $this->pdo()->query(
            'SELECT migration FROM ' . $this->wrap($this->table) . ' ORDER BY batch ASC, id ASC',
        );
        if ($stmt === false) {
            return [];
        }

        return array_map(static fn ($row) => (string) $row['migration'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<array{migration: string, batch: int, applied_at: string}>
     */
    public function getMigrationRows(): array
    {
        $this->createRepository();
        $stmt = $this->pdo()->query(
            'SELECT migration, batch, applied_at FROM ' . $this->wrap($this->table) . ' ORDER BY batch ASC, id ASC',
        );
        if ($stmt === false) {
            return [];
        }

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'migration' => (string) $row['migration'],
                'batch' => (int) $row['batch'],
                'applied_at' => (string) $row['applied_at'],
            ];
        }

        return $rows;
    }

    public function getNextBatchNumber(): int
    {
        $this->createRepository();
        $stmt = $this->pdo()->query('SELECT MAX(batch) AS m FROM ' . $this->wrap($this->table));
        if ($stmt === false) {
            return 1;
        }
        $max = $stmt->fetch(PDO::FETCH_ASSOC);

        return ((int) ($max['m'] ?? 0)) + 1;
    }

    /**
     * @return list<array{migration: string, batch: int}>
     */
    public function getMigrationsByBatch(int $batch): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT migration, batch FROM ' . $this->wrap($this->table) . ' WHERE batch = ? ORDER BY id DESC',
        );
        $stmt->execute([$batch]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'migration' => (string) $row['migration'],
                'batch' => (int) $row['batch'],
            ];
        }

        return $rows;
    }

    public function getLastBatchNumber(): int
    {
        $this->createRepository();
        $stmt = $this->pdo()->query('SELECT MAX(batch) AS m FROM ' . $this->wrap($this->table));
        if ($stmt === false) {
            return 0;
        }
        $max = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($max['m'] ?? 0);
    }

    public function log(string $migration, int $batch): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO ' . $this->wrap($this->table) . ' (migration, batch, applied_at) VALUES (?, ?, CURRENT_TIMESTAMP)',
        );
        $stmt->execute([$migration, $batch]);
    }

    public function delete(string $migration): void
    {
        $stmt = $this->pdo()->prepare(
            'DELETE FROM ' . $this->wrap($this->table) . ' WHERE migration = ?',
        );
        $stmt->execute([$migration]);
    }

    public function deleteRepository(): void
    {
        if ($this->repositoryExists()) {
            $this->pdo()->exec('DROP TABLE ' . $this->wrap($this->table));
        }
    }

    private function pdo(): PDO
    {
        $pdo = $this->connection->getPdo();
        if (!$pdo instanceof PDO) {
            throw new MigrationException('Database connection has no PDO instance.');
        }

        return $pdo;
    }

    private function wrap(string $value): string
    {
        $driver = $this->connection->getType();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return '`' . str_replace('`', '``', $value) . '`';
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
