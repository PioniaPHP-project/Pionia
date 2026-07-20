<?php

namespace Pionia\Database\Grammar;

use Pionia\Database\Blueprint;
use Pionia\Database\ColumnDefinition;
use Pionia\Database\ForeignKeyDefinition;
use Pionia\Database\IndexDefinition;
use Pionia\Database\Migrations\MigrationException;

/**
 * SQLite SQL compiler for schema blueprints.
 */
final class SqliteGrammar extends Grammar
{
    public function wrap(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    protected function compileCreate(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            if ($column->isChange()) {
                continue;
            }
            $columns[] = $this->compileColumnDefinition($column);
        }

        foreach ($blueprint->getIndexes() as $index) {
            if ($index->getType() === 'primary' && count($index->getColumns()) > 1) {
                $columns[] = 'PRIMARY KEY (' . $this->columnize($index->getColumns()) . ')';
            }
        }

        foreach ($blueprint->getForeignKeys() as $fk) {
            $columns[] = $this->compileForeignInline($blueprint->getTable(), $fk);
        }

        $sql = ["CREATE TABLE {$table} (" . implode(', ', $columns) . ')'];

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

        foreach ($blueprint->getCommands() as $command) {
            match ($command['type']) {
                'addColumn' => null, // handled via columns below
                'dropColumn' => $statements = array_merge(
                    $statements,
                    $this->compileDropColumns($table, $command['columns'] ?? []),
                ),
                'renameColumn' => $statements[] = sprintf(
                    'ALTER TABLE %s RENAME COLUMN %s TO %s',
                    $wrapped,
                    $this->wrap($command['from'] ?? ''),
                    $this->wrap($command['to'] ?? ''),
                ),
                'addIndex' => $statements[] = $this->compileCreateIndex($table, $command['index']),
                'dropIndex', 'dropUnique' => $statements[] = 'DROP INDEX IF EXISTS ' . $this->wrap((string) ($command['name'] ?? '')),
                'dropPrimary' => throw new MigrationException('SQLite does not support DROP PRIMARY KEY via ALTER.'),
                'foreign' => $statements[] = $this->compileAddForeign($table, $command['foreign']),
                'dropForeign' => throw new MigrationException('SQLite does not support DROP FOREIGN KEY via ALTER; recreate the table.'),
                default => null,
            };
        }

        // Add columns that were registered on the blueprint during alter
        $addedNames = [];
        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'addColumn') {
                foreach ($command['columns'] ?? [] as $name) {
                    $addedNames[$name] = true;
                }
            }
        }

        foreach ($blueprint->getColumns() as $column) {
            if (!isset($addedNames[$column->getName()])) {
                continue;
            }
            if ($column->isChange()) {
                throw new MigrationException('SQLite does not support MODIFY COLUMN; recreate the table.');
            }
            $statements[] = sprintf(
                'ALTER TABLE %s ADD COLUMN %s',
                $wrapped,
                $this->compileColumnDefinition($column),
            );
        }

        // Indexes / FKs already queued via commands when blueprint->index/foreign called
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
        return 'ALTER TABLE ' . $this->wrapTable($from) . ' RENAME TO ' . $this->wrap($this->tablePrefix . $to);
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $this->quote($table);
    }

    public function compileColumnListing(string $table): string
    {
        return 'PRAGMA table_info(' . $this->wrap($table) . ')';
    }

    /**
     * SQLite PRAGMA returns cid, name, type, … — callers read the `name` field.
     */
    public function columnListingNameIndex(): int
    {
        return 1;
    }

    private function compileColumnDefinition(ColumnDefinition $column): string
    {
        if ($column->getType() === 'id' || ($column->isAutoIncrement() && $column->isPrimary())) {
            return $this->wrap($column->getName()) . ' INTEGER PRIMARY KEY AUTOINCREMENT';
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

    private function compileForeignInline(string $table, ForeignKeyDefinition $fk): string
    {
        if ($fk->getOn() === null) {
            throw new MigrationException('Foreign key requires ->on($table).');
        }

        $sql = sprintf(
            'FOREIGN KEY (%s) REFERENCES %s (%s)',
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

    private function compileAddForeign(string $table, ForeignKeyDefinition $fk): string
    {
        // SQLite cannot add FK via ALTER; document as exception for alter path
        throw new MigrationException(
            'SQLite cannot add foreign keys with ALTER TABLE; define them in Schema::create().',
        );
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private function compileDropColumns(string $table, array $columns): array
    {
        $wrapped = $this->wrapTable($table);
        $sql = [];
        foreach ($columns as $column) {
            $sql[] = sprintf('ALTER TABLE %s DROP COLUMN %s', $wrapped, $this->wrap($column));
        }

        return $sql;
    }

    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    protected function typeId(ColumnDefinition $column): string
    {
        return 'INTEGER';
    }

    protected function typeUuid(ColumnDefinition $column): string
    {
        return 'VARCHAR(36)';
    }

    protected function typeUlid(ColumnDefinition $column): string
    {
        return 'VARCHAR(26)';
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
        return 'INTEGER';
    }

    protected function typeSmallInteger(ColumnDefinition $column): string
    {
        return 'INTEGER';
    }

    protected function typeTinyInteger(ColumnDefinition $column): string
    {
        return 'INTEGER';
    }

    protected function typeBoolean(ColumnDefinition $column): string
    {
        return 'INTEGER';
    }

    protected function typeFloat(ColumnDefinition $column): string
    {
        return 'REAL';
    }

    protected function typeDouble(ColumnDefinition $column): string
    {
        return 'REAL';
    }

    protected function typeDecimal(ColumnDefinition $column): string
    {
        return 'NUMERIC(' . $column->getPrecision() . ', ' . $column->getScale() . ')';
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
        return 'DATETIME';
    }

    protected function typeJson(ColumnDefinition $column): string
    {
        return 'TEXT';
    }

    protected function typeJsonb(ColumnDefinition $column): string
    {
        return 'TEXT';
    }

    protected function typeBinary(ColumnDefinition $column): string
    {
        return 'BLOB';
    }

    protected function typeEnum(ColumnDefinition $column): string
    {
        $allowed = $column->getAllowed() ?? [];
        $list = implode(', ', array_map(fn (string $v) => $this->quote($v), $allowed));

        return 'TEXT CHECK (' . $this->wrap($column->getName()) . ' IN (' . $list . '))';
    }

    protected function typeForeignId(ColumnDefinition $column): string
    {
        return 'INTEGER';
    }
}
