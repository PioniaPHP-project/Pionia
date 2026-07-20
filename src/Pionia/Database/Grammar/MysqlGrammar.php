<?php

namespace Pionia\Database\Grammar;

use Pionia\Database\Blueprint;
use Pionia\Database\ColumnDefinition;
use Pionia\Database\ForeignKeyDefinition;
use Pionia\Database\IndexDefinition;
use Pionia\Database\Migrations\MigrationException;

/**
 * MySQL / MariaDB SQL compiler for schema blueprints.
 */
final class MysqlGrammar extends Grammar
{
    public function wrap(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
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
            } elseif ($index->getType() !== 'primary') {
                // emitted as separate statements after CREATE for clarity with names
            }
        }

        foreach ($blueprint->getForeignKeys() as $fk) {
            $parts[] = $this->compileForeignConstraint($blueprint->getTable(), $fk);
        }

        $sql = ["CREATE TABLE {$table} (" . implode(', ', $parts) . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'];

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
            $verb = $column->isChange() ? 'MODIFY' : 'ADD';
            $def = $this->compileColumnDefinition($column);
            $after = $column->getAfter() !== null ? ' AFTER ' . $this->wrap($column->getAfter()) : '';
            $statements[] = "ALTER TABLE {$wrapped} {$verb} COLUMN {$def}{$after}";
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
                'dropIndex', 'dropUnique' => $statements[] = sprintf(
                    'ALTER TABLE %s DROP INDEX %s',
                    $wrapped,
                    $this->wrap((string) ($command['name'] ?? '')),
                ),
                'dropPrimary' => $statements[] = "ALTER TABLE {$wrapped} DROP PRIMARY KEY",
                'foreign' => $statements[] = sprintf(
                    'ALTER TABLE %s ADD %s',
                    $wrapped,
                    $this->compileForeignConstraint($table, $command['foreign']),
                ),
                'dropForeign' => $statements[] = sprintf(
                    'ALTER TABLE %s DROP FOREIGN KEY %s',
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
        return 'DROP TABLE ' . $this->wrapTable($table);
    }

    public function compileDropIfExists(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrapTable($table);
    }

    public function compileRename(string $from, string $to): string
    {
        return 'RENAME TABLE ' . $this->wrapTable($from) . ' TO ' . $this->wrapTable($to);
    }

    public function compileTableExists(string $table): string
    {
        return 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $this->quote($table);
    }

    public function compileColumnListing(string $table): string
    {
        return 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $this->quote($table);
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
        $sql = $this->wrap($column->getName()) . ' ' . $this->getType($column);

        if ($column->getType() === 'id' || ($column->isAutoIncrement() && $column->isPrimary())) {
            return $this->wrap($column->getName()) . ' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        }

        if ($column->isUnsigned() && in_array($column->getType(), ['integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'foreignId'], true)) {
            // type methods already append UNSIGNED where needed
        }

        $sql = $this->addModifiers($sql, $column);

        if ($column->isPrimary() && $column->getType() !== 'id') {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->getComment() !== null) {
            $sql .= ' COMMENT ' . $this->quote($column->getComment());
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
        return 'BIGINT UNSIGNED';
    }

    protected function typeUuid(ColumnDefinition $column): string
    {
        return 'CHAR(36)';
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
        return ($column->isUnsigned() ? 'INT UNSIGNED' : 'INT');
    }

    protected function typeBigInteger(ColumnDefinition $column): string
    {
        return ($column->isUnsigned() ? 'BIGINT UNSIGNED' : 'BIGINT');
    }

    protected function typeSmallInteger(ColumnDefinition $column): string
    {
        return ($column->isUnsigned() ? 'SMALLINT UNSIGNED' : 'SMALLINT');
    }

    protected function typeTinyInteger(ColumnDefinition $column): string
    {
        return ($column->isUnsigned() ? 'TINYINT UNSIGNED' : 'TINYINT');
    }

    protected function typeBoolean(ColumnDefinition $column): string
    {
        return 'TINYINT(1)';
    }

    protected function typeFloat(ColumnDefinition $column): string
    {
        return 'DOUBLE';
    }

    protected function typeDouble(ColumnDefinition $column): string
    {
        return 'DOUBLE';
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
        return 'DATETIME';
    }

    protected function typeTimestamp(ColumnDefinition $column): string
    {
        return $column->getType() === 'timestampTz' ? 'TIMESTAMP' : 'TIMESTAMP';
    }

    protected function typeJson(ColumnDefinition $column): string
    {
        return 'JSON';
    }

    protected function typeJsonb(ColumnDefinition $column): string
    {
        return 'JSON';
    }

    protected function typeBinary(ColumnDefinition $column): string
    {
        return 'BLOB';
    }

    protected function typeEnum(ColumnDefinition $column): string
    {
        $allowed = $column->getAllowed() ?? [];
        $list = implode(', ', array_map(fn (string $v) => $this->quote($v), $allowed));

        return 'ENUM(' . $list . ')';
    }

    protected function typeForeignId(ColumnDefinition $column): string
    {
        return 'BIGINT UNSIGNED';
    }
}
