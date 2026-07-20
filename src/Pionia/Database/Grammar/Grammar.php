<?php

namespace Pionia\Database\Grammar;

use Pionia\Database\Blueprint;
use Pionia\Database\ColumnDefinition;
use Pionia\Database\ForeignKeyDefinition;
use Pionia\Database\IndexDefinition;
use Pionia\Database\Migrations\MigrationException;

/**
 * Compiles a {@see Blueprint} into ordered SQL statements for a specific driver.
 */
abstract class Grammar
{
    public function __construct(
        protected readonly string $tablePrefix = '',
    ) {
    }

    /**
     * @return list<string>
     */
    public function compile(Blueprint $blueprint): array
    {
        if ($blueprint->isCreating()) {
            return $this->compileCreate($blueprint);
        }

        return $this->compileAlter($blueprint);
    }

    /**
     * @return list<string>
     */
    abstract protected function compileCreate(Blueprint $blueprint): array;

    /**
     * @return list<string>
     */
    abstract protected function compileAlter(Blueprint $blueprint): array;

    abstract public function compileDrop(string $table): string;

    abstract public function compileDropIfExists(string $table): string;

    abstract public function compileRename(string $from, string $to): string;

    abstract public function compileTableExists(string $table): string;

    /**
     * @return list<string> SQL that returns rows; first column is the column name.
     */
    abstract public function compileColumnListing(string $table): string;

    abstract public function wrap(string $value): string;

    public function wrapTable(string $table): string
    {
        return $this->wrap($this->tablePrefix . $table);
    }

    /**
     * @param list<string> $values
     */
    protected function columnize(array $values): string
    {
        return implode(', ', array_map(fn (string $v) => $this->wrap($v), $values));
    }

    protected function getType(ColumnDefinition $column): string
    {
        return match ($column->getType()) {
            'id' => $this->typeId($column),
            'uuid' => $this->typeUuid($column),
            'ulid' => $this->typeUlid($column),
            'string' => $this->typeString($column),
            'text' => $this->typeText($column),
            'integer' => $this->typeInteger($column),
            'bigInteger' => $this->typeBigInteger($column),
            'smallInteger' => $this->typeSmallInteger($column),
            'tinyInteger' => $this->typeTinyInteger($column),
            'boolean' => $this->typeBoolean($column),
            'float' => $this->typeFloat($column),
            'double' => $this->typeDouble($column),
            'decimal' => $this->typeDecimal($column),
            'date' => $this->typeDate($column),
            'dateTime' => $this->typeDateTime($column),
            'timestamp', 'timestampTz' => $this->typeTimestamp($column),
            'json' => $this->typeJson($column),
            'jsonb' => $this->typeJsonb($column),
            'binary' => $this->typeBinary($column),
            'enum' => $this->typeEnum($column),
            'foreignId' => $this->typeForeignId($column),
            default => throw new MigrationException("Unsupported column type [{$column->getType()}]."),
        };
    }

    abstract protected function typeId(ColumnDefinition $column): string;

    abstract protected function typeUuid(ColumnDefinition $column): string;

    abstract protected function typeUlid(ColumnDefinition $column): string;

    abstract protected function typeString(ColumnDefinition $column): string;

    abstract protected function typeText(ColumnDefinition $column): string;

    abstract protected function typeInteger(ColumnDefinition $column): string;

    abstract protected function typeBigInteger(ColumnDefinition $column): string;

    abstract protected function typeSmallInteger(ColumnDefinition $column): string;

    abstract protected function typeTinyInteger(ColumnDefinition $column): string;

    abstract protected function typeBoolean(ColumnDefinition $column): string;

    abstract protected function typeFloat(ColumnDefinition $column): string;

    abstract protected function typeDouble(ColumnDefinition $column): string;

    abstract protected function typeDecimal(ColumnDefinition $column): string;

    abstract protected function typeDate(ColumnDefinition $column): string;

    abstract protected function typeDateTime(ColumnDefinition $column): string;

    abstract protected function typeTimestamp(ColumnDefinition $column): string;

    abstract protected function typeJson(ColumnDefinition $column): string;

    abstract protected function typeJsonb(ColumnDefinition $column): string;

    abstract protected function typeBinary(ColumnDefinition $column): string;

    abstract protected function typeEnum(ColumnDefinition $column): string;

    abstract protected function typeForeignId(ColumnDefinition $column): string;

    protected function addModifiers(string $sql, ColumnDefinition $column): string
    {
        if ($column->isNullable()) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        if ($column->hasDefault()) {
            $sql .= ' DEFAULT ' . $this->formatDefault($column->getDefault());
        }

        if ($column->isUnique() && !$column->isPrimary()) {
            $sql .= ' UNIQUE';
        }

        if ($column->getCheck() !== null && $column->getCheck() !== '') {
            $expr = str_replace(
                '{column}',
                $this->wrap($column->getName()),
                $column->getCheck(),
            );
            $sql .= ' CHECK (' . $expr . ')';
        }

        return $sql;
    }

    protected function formatDefault(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value) && strtoupper($value) === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    protected function indexName(string $table, IndexDefinition $index): string
    {
        if ($index->getName() !== null) {
            return $index->getName();
        }

        $cols = implode('_', $index->getColumns());

        return $table . '_' . $cols . '_' . $index->getType();
    }

    protected function foreignKeyName(string $table, ForeignKeyDefinition $fk): string
    {
        if ($fk->getName() !== null) {
            return $fk->getName();
        }

        return $table . '_' . implode('_', $fk->getColumns()) . '_foreign';
    }

    /**
     * Skip indexes already expressed as column UNIQUE modifiers during CREATE.
     *
     * @return list<IndexDefinition>
     */
    protected function indexesForCreate(Blueprint $blueprint): array
    {
        $columnUniques = [];
        $columnIndexes = [];
        foreach ($blueprint->getColumns() as $column) {
            if ($column->isUnique()) {
                $columnUniques[$column->getName()] = true;
            }
            if ($column->isIndex()) {
                $columnIndexes[$column->getName()] = true;
            }
        }

        $result = [];
        foreach ($blueprint->getIndexes() as $index) {
            $cols = $index->getColumns();
            if (count($cols) === 1) {
                if ($index->getType() === 'unique' && isset($columnUniques[$cols[0]])) {
                    continue;
                }
                if ($index->getType() === 'index' && isset($columnIndexes[$cols[0]])) {
                    continue;
                }
            }
            $result[] = $index;
        }

        // Single-column indexes requested via ->index() on the column
        foreach ($blueprint->getColumns() as $column) {
            if ($column->isIndex() && !$column->isUnique() && !$column->isPrimary()) {
                $already = false;
                foreach ($result as $idx) {
                    if ($idx->getType() === 'index' && $idx->getColumns() === [$column->getName()]) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $result[] = new IndexDefinition([$column->getName()], 'index');
                }
            }
        }

        return $result;
    }
}
