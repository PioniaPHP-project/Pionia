<?php

namespace Pionia\Database;

use Closure;
use PDO;
use Pionia\Database\Grammar\Grammar;
use Pionia\Database\Grammar\MysqlGrammar;
use Pionia\Database\Grammar\PgsqlGrammar;
use Pionia\Database\Grammar\SqliteGrammar;
use Pionia\Database\Migrations\MigrationException;
use Pionia\Porm\Driver\Connection;

/**
 * Static facade for schema operations (create / alter / drop / inspect).
 *
 * Uses {@see connectionManager()} and compiles {@see Blueprint} via driver grammars.
 */
final class Schema
{
    private static ?string $connection = null;

    /**
     * Run the blueprint against a new table.
     *
     * @param Closure(Blueprint): void $callback
     */
    public static function create(string $table, Closure $callback, ?string $connection = null): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->creating(true);
        $callback($blueprint);
        self::build($blueprint, $connection);
    }

    /**
     * Alter an existing table.
     *
     * @param Closure(Blueprint): void $callback
     */
    public static function table(string $table, Closure $callback, ?string $connection = null): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->creating(false);
        $callback($blueprint);
        self::build($blueprint, $connection);
    }

    public static function drop(string $table, ?string $connection = null): void
    {
        $grammar = self::grammar($connection);
        self::run($grammar->compileDrop($table), $connection);
    }

    public static function dropIfExists(string $table, ?string $connection = null): void
    {
        $grammar = self::grammar($connection);
        self::run($grammar->compileDropIfExists($table), $connection);
    }

    public static function rename(string $from, string $to, ?string $connection = null): void
    {
        $grammar = self::grammar($connection);
        self::run($grammar->compileRename($from, $to), $connection);
    }

    public static function hasTable(string $table, ?string $connection = null): bool
    {
        $grammar = self::grammar($connection);
        $pdo = self::pdo($connection);
        $stmt = $pdo->query($grammar->compileTableExists($table));
        if ($stmt === false) {
            return false;
        }

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function hasColumn(string $table, string $column, ?string $connection = null): bool
    {
        $columns = self::getColumnListing($table, $connection);

        return in_array($column, $columns, true);
    }

    /**
     * @return list<string>
     */
    public static function getColumnListing(string $table, ?string $connection = null): array
    {
        $grammar = self::grammar($connection);
        $pdo = self::pdo($connection);
        $stmt = $pdo->query($grammar->compileColumnListing($table));
        if ($stmt === false) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $names = [];
        foreach ($rows as $row) {
            // sqlite PRAGMA: name; mysql/pgsql: COLUMN_NAME / column_name
            $names[] = (string) ($row['name'] ?? $row['COLUMN_NAME'] ?? $row['column_name'] ?? array_values($row)[0] ?? '');
        }

        return array_values(array_filter($names, fn (string $n) => $n !== ''));
    }

    public static function raw(string $sql, ?string $connection = null): void
    {
        self::run($sql, $connection);
    }

    /**
     * Scope subsequent Schema calls to a named connection (or pass null to reset).
     */
    public static function connection(?string $name): void
    {
        self::$connection = $name;
    }

    public static function getConnectionName(): ?string
    {
        return self::$connection;
    }

    public static function grammarForDriver(string $driver, string $prefix = ''): Grammar
    {
        return match (strtolower($driver)) {
            'sqlite' => new SqliteGrammar($prefix),
            'mysql', 'mariadb' => new MysqlGrammar($prefix),
            'pgsql', 'postgres', 'postgresql' => new PgsqlGrammar($prefix),
            default => throw new MigrationException("Unsupported database driver [{$driver}] for schema operations."),
        };
    }

    private static function build(Blueprint $blueprint, ?string $connection): void
    {
        $grammar = self::grammar($connection);
        foreach ($grammar->compile($blueprint) as $sql) {
            self::run($sql, $connection);
        }
    }

    private static function grammar(?string $connection): Grammar
    {
        $name = $connection ?? self::$connection;
        // Always resolve from live connection so tests swapping PDO stay correct
        $conn = self::resolveConnection($name);
        $prefix = $conn->getPrefix() ?? '';

        return self::grammarForDriver($conn->getType(), $prefix);
    }

    private static function pdo(?string $connection): PDO
    {
        $pdo = self::resolveConnection($connection ?? self::$connection)->getPdo();
        if (!$pdo instanceof PDO) {
            throw new MigrationException('Database connection has no PDO instance.');
        }

        return $pdo;
    }

    private static function resolveConnection(?string $name): Connection
    {
        $connection = connectionManager()->connection($name ?? 'default');
        if ($connection->getType() === 'sqlite') {
            $pdo = $connection->getPdo();
            if ($pdo instanceof PDO) {
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
        }

        return $connection;
    }

    private static function run(string $sql, ?string $connection): void
    {
        $pdo = self::pdo($connection);
        $pdo->exec($sql);
    }
}
