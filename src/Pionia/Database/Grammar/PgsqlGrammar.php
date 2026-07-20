<?php

namespace Pionia\Database\Grammar;

use Pionia\Database\Blueprint;
use Pionia\Database\ColumnDefinition;
use Pionia\Database\ForeignKeyDefinition;
use Pionia\Database\IndexDefinition;
use Pionia\Database\Migrations\MigrationException;

/**
 * PostgreSQL SQL compiler for schema blueprints.
 */
final class PgsqlGrammar extends Grammar
{
    public function wrap(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    protected function compileCreate(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $parts = [];

        foreach ($blueprint->getColumns() as $column) {
            $parts[] = $this->compileColumnDefinition($column);
        }

        foreach ($blueprint->getIndexes() as $index) {
            if ($index->getType() === 'primary' && !$this->hasPrimaryColumn($blueprint)) {
                $parts[] = 'PRIMARY KEY (' . $this->columnize($index->getColumns()) . ')';
            }
        }

        foreach ($blueprint->getForeignKeys() as $fk) {
            $parts[] = $this->compileForeignConstraint($blueprint->getTable(), $fk);
        }

        $sql = ["CREATE TABLE {$table} (" . implode(', ', $parts) . ')'];

        foreach ($this->indexesForCreate($blueprint) as $index) {
            if ($index->getType() === 'primary') {
                continue;
            }
            $sql[] = $this->compileCreateIndex($blueprint->getTable(), $index);
        }

        return $sql;
    }

    protected function compileAlter(Blueprint $blueprint): array
    {
        $statements = [];
        $table = $blueprint->getTable();
        $wrapped = $this->wrapTable($table);

        $added = [];
        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'addColumn') {
                foreach ($command['columns'] ?? [] as $name) {
                    $added[$name] = true;
                }
            }
        }

        foreach ($blueprint->getColumns() as $column) {
            if (!isset($added[$column->getName()])) {
                continue;
            }
            if ($column->isChange()) {
                $statements[] = sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE %s',
                    $wrapped,
                    $this->wrap($column->getName()),
                    $this->getType($column),
                );
                continue;
            }
            $statements[] = sprintf(
                'ALTER TABLE %s ADD COLUMN %s',
                $wrapped,
                $this->compileColumnDefinition($column),
            );
        }

        foreach ($blueprint->getCommands() as $command) {
            match ($command['type']) {
                'dropColumn' => $statements[] = sprintf(
                    'ALTER TABLE %s DROP COLUMN %s',
                    $wrapped,
                    $this->columnize($command['columns'] ?? []),
                ),
                'renameColumn' => $statements[] = sprintf(
                    'ALTER TABLE %s RENAME COLUMN %s TO %s',
                    $wrapped,
                    $this->wrap($command['from'] ?? ''),
                    $this->wrap($command['to'] ?? ''),
                ),
                'addIndex' => $statements[] = $this->compileCreateIndex($table, $command['index']),
                'dropIndex', 'dropUnique' => $statements[] = 'DROP INDEX IF EXISTS ' . $this->wrap((string) ($command['name'] ?? '')),
                'dropPrimary' => $statements[] = sprintf(
                    'ALTER TABLE %s DROP CONSTRAINT %s',
                    $wrapped,
                    $this->wrap($command['name'] ?? ($table . '_pkey')),
                ),
                'foreign' => $statements[] = sprintf(
                    'ALTER TABLE %s ADD %s',
                    $wrapped,
                    $this->compileForeignConstraint($table, $command['foreign']),
                ),
                'dropForeign' => $statements[] = sprintf(
                    'ALTER TABLE %s DROP CONSTRAINT %s',
                    $wrapped,
                    $this->wrap((string) ($command['name'] ?? '')),
                ),
                default => null,
            };
        }

        return array_values(array_filter($statements));
    }

    public function compileDrop(string $table): string
    {
        return 'DROP TABLE ' . $this->wrapTable($table) . ' CASCADE';
    }

    public function compileDropIfExists(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrapTable($table) . ' CASCADE';
    }

    public function compileRename(string $from, string $to): string
    {
        return 'ALTER TABLE ' . $this->wrapTable($from) . ' RENAME TO ' . $this->wrap($this->tablePrefix . $to);
    }

    public function compileTableExists(string $table): string
    {
        return 'SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = current_schema() AND tablename = ' . $this->quote($table);
    }

    public function compileColumnListing(string $table): string
    {
        return 'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ' . $this->quote($table);
    }

    private function hasPrimaryColumn(Blueprint $blueprint): bool
    {
        foreach ($blueprint->getColumns() as $column) {
            if ($column->isPrimary() || $column->getType() === 'id') {
                return true;
            }
        }

        return false;
    }

    private function compileColumnDefinition(ColumnDefinition $column): string
    {
        if ($column->getType() === 'id' || ($column->isAutoIncrement() && $column->isPrimary())) {
            return $this->wrap($column->getName()) . ' BIGSERIAL PRIMARY KEY';
        }

        $sql = $this->wrap($column->getName()) . ' ' . $this->getType($column);
        $sql = $this->addModifiers($sql, $column);

        if ($column->isPrimary() && $column->getType() !== 'id') {
            $sql .= ' PRIMARY KEY';
        }

        return $sql;
    }

    private function compileCreateIndex(string $table, IndexDefinition $index): string
    {
        $unique = $index->getType() === 'unique' ? 'UNIQUE ' : '';
        $name = $this->wrap($this->indexName($table, $index));

        return sprintf(
            'CREATE %sINDEX %s ON %s (%s)',
            $unique,
            $name,
            $this->wrapTable($table),
            $this->columnize($index->getColumns()),
        );
    }

    private function compileForeignConstraint(string $table, ForeignKeyDefinition $fk): string
    {
        if ($fk->getOn() === null) {
            throw new MigrationException('Foreign key requires ->on($table).');
        }

        $sql = sprintf(
            'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->wrap($this->foreignKeyName($table, $fk)),
            $this->columnize($fk->getColumns()),
            $this->wrapTable($fk->getOn()),
            $this->wrap($fk->getReferences()),
        );

        if ($fk->getOnDelete() !== null) {
            $sql .= ' ON DELETE ' . strtoupper($fk->getOnDelete());
        }
        if ($fk->getOnUpdate() !== null) {
            $sql .= ' ON UPDATE ' . strtoupper($fk->getOnUpdate());
        }

        return $sql;
    }

    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    protected function typeId(ColumnDefinition $column): string
    {
        return 'BIGINT';
    }

    protected function typeUuid(ColumnDefinition $column): string
    {
        return 'UUID';
    }

    protected function typeUlid(ColumnDefinition $column): string
    {
        return 'CHAR(26)';
    }

    protected function typeString(ColumnDefinition $column): string
    {
        return 'VARCHAR(' . $column->getLength() . ')';
    }

    protected function typeText(ColumnDefinition $column): string
    {
        return 'TEXT';
    }

    protected function typeInteger(ColumnDefinition $column): string
    {
        return 'INTEGER';
    }

    protected function typeBigInteger(ColumnDefinition $column): string
    {
        return 'BIGINT';
    }

    protected function typeSmallInteger(ColumnDefinition $column): string
    {
        return 'SMALLINT';
    }

    protected function typeTinyInteger(ColumnDefinition $column): string
    {
        return 'SMALLINT';
    }

    protected function typeBoolean(ColumnDefinition $column): string
    {
        return 'BOOLEAN';
    }

    protected function typeFloat(ColumnDefinition $column): string
    {
        return 'DOUBLE PRECISION';
    }

    protected function typeDouble(ColumnDefinition $column): string
    {
        return 'DOUBLE PRECISION';
    }

    protected function typeDecimal(ColumnDefinition $column): string
    {
        return 'DECIMAL(' . $column->getPrecision() . ', ' . $column->getScale() . ')';
    }

    protected function typeDate(ColumnDefinition $column): string
    {
        return 'DATE';
    }

    protected function typeDateTime(ColumnDefinition $column): string
    {
        return 'TIMESTAMP(0) WITHOUT TIME ZONE';
    }

    protected function typeTimestamp(ColumnDefinition $column): string
    {
        return $column->getType() === 'timestampTz'
            ? 'TIMESTAMP(0) WITH TIME ZONE'
            : 'TIMESTAMP(0) WITHOUT TIME ZONE';
    }

    protected function typeJson(ColumnDefinition $column): string
    {
        return 'JSON';
    }

    protected function typeJsonb(ColumnDefinition $column): string
    {
        return 'JSONB';
    }

    protected function typeBinary(ColumnDefinition $column): string
    {
        return 'BYTEA';
    }

    protected function typeEnum(ColumnDefinition $column): string
    {
        // PostgreSQL enums are types; store as CHECK for portability in migrations
        $allowed = $column->getAllowed() ?? [];
        $list = implode(', ', array_map(fn (string $v) => $this->quote($v), $allowed));

        return 'VARCHAR(255) CHECK (' . $this->wrap($column->getName()) . ' IN (' . $list . '))';
    }

    protected function typeForeignId(ColumnDefinition $column): string
    {
        return 'BIGINT';
    }
}
