<?php

namespace Pionia\Database;

/**
 * Fluent table builder used by {@see Schema::create()} and {@see Schema::table()}.
 *
 * Commands are collected in order and compiled by a driver {@see Grammar\Grammar}.
 *
 * @see Grammar\Grammar
 */
final class Blueprint
{
    /** @var list<ColumnDefinition> */
    private array $columns = [];

    /** @var list<array{type: string, columns?: list<string>, from?: string, to?: string, name?: string|null, index?: IndexDefinition, foreign?: ForeignKeyDefinition}> */
    private array $commands = [];

    /** @var list<IndexDefinition> */
    private array $indexes = [];

    /** @var list<ForeignKeyDefinition> */
    private array $foreignKeys = [];

    private bool $creating = false;

    public function __construct(
        private readonly string $table,
    ) {
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function creating(bool $value = true): self
    {
        $this->creating = $value;

        return $this;
    }

    public function isCreating(): bool
    {
        return $this->creating;
    }

    /** @return list<ColumnDefinition> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<array{type: string, columns?: list<string>, from?: string, to?: string, name?: string|null, index?: IndexDefinition, foreign?: ForeignKeyDefinition}>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /** @return list<IndexDefinition> */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /** @return list<ForeignKeyDefinition> */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    // --- Columns -----------------------------------------------------------------

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn('id', $name, ['autoIncrement' => true])->primary();
    }

    public function uuid(string $name = 'uuid'): ColumnDefinition
    {
        return $this->addColumn('uuid', $name);
    }

    public function ulid(string $name = 'ulid'): ColumnDefinition
    {
        return $this->addColumn('ulid', $name);
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $name, ['length' => $length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn('text', $name);
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn('integer', $name);
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $name);
    }

    public function smallInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $name);
    }

    public function tinyInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $name);
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn('boolean', $name);
    }

    public function float(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('float', $name, ['precision' => $precision, 'scale' => $scale]);
    }

    public function double(string $name, ?int $precision = null, ?int $scale = null): ColumnDefinition
    {
        return $this->addColumn('double', $name, [
            'precision' => $precision ?? 15,
            'scale' => $scale ?? 8,
        ]);
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $name, [
            'precision' => $precision,
            'scale' => $scale,
            'total' => $precision,
            'places' => $scale,
        ]);
    }

    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn('date', $name);
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->addColumn('dateTime', $name);
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn('timestamp', $name);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function timestampsTz(): void
    {
        $this->addColumn('timestampTz', 'created_at')->nullable();
        $this->addColumn('timestampTz', 'updated_at')->nullable();
    }

    public function softDeletes(string $column = 'deleted_at'): ColumnDefinition
    {
        return $this->timestamp($column)->nullable();
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn('json', $name);
    }

    public function binary(string $name): ColumnDefinition
    {
        return $this->addColumn('binary', $name);
    }

    /**
     * @param list<string> $values
     */
    public function enum(string $name, array $values): ColumnDefinition
    {
        return $this->addColumn('enum', $name, ['allowed' => $values]);
    }

    public function foreignId(string $name): ColumnDefinition
    {
        return $this->addColumn('foreignId', $name, ['unsigned' => true]);
    }

    // --- Alter helpers -----------------------------------------------------------

    /**
     * @param list<string>|string $columns
     */
    public function dropColumn(array|string $columns): self
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $this->commands[] = ['type' => 'dropColumn', 'columns' => array_values($cols)];

        return $this;
    }

    public function renameColumn(string $from, string $to): self
    {
        $this->commands[] = ['type' => 'renameColumn', 'from' => $from, 'to' => $to];

        return $this;
    }

    // --- Indexes -----------------------------------------------------------------

    /**
     * @param list<string>|string $columns
     */
    public function index(array|string $columns, ?string $name = null): IndexDefinition
    {
        return $this->addIndex($columns, 'index', $name);
    }

    /**
     * @param list<string>|string $columns
     */
    public function unique(array|string $columns, ?string $name = null): IndexDefinition
    {
        return $this->addIndex($columns, 'unique', $name);
    }

    /**
     * @param list<string>|string $columns
     */
    public function primary(array|string $columns, ?string $name = null): IndexDefinition
    {
        return $this->addIndex($columns, 'primary', $name);
    }

    public function dropIndex(string $name): self
    {
        $this->commands[] = ['type' => 'dropIndex', 'name' => $name];

        return $this;
    }

    public function dropUnique(string $name): self
    {
        $this->commands[] = ['type' => 'dropUnique', 'name' => $name];

        return $this;
    }

    public function dropPrimary(?string $name = null): self
    {
        $this->commands[] = ['type' => 'dropPrimary', 'name' => $name];

        return $this;
    }

    // --- Foreign keys ------------------------------------------------------------

    /**
     * @param list<string>|string $columns
     */
    public function foreign(array|string $columns): ForeignKeyDefinition
    {
        $cols = is_array($columns) ? array_values($columns) : [$columns];
        $fk = new ForeignKeyDefinition($this, $cols);
        $this->foreignKeys[] = $fk;
        $this->commands[] = ['type' => 'foreign', 'foreign' => $fk];

        return $fk;
    }

    public function dropForeign(string $name): self
    {
        $this->commands[] = ['type' => 'dropForeign', 'name' => $name];

        return $this;
    }

    // --- Internals ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $parameters
     */
    public function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition($this, $type, $name, $parameters);
        $this->columns[] = $column;
        $this->commands[] = ['type' => 'addColumn', 'columns' => [$name]];

        return $column;
    }

    /**
     * @param list<string>|string $columns
     * @param 'index'|'unique'|'primary' $type
     */
    private function addIndex(array|string $columns, string $type, ?string $name): IndexDefinition
    {
        $cols = is_array($columns) ? array_values($columns) : [$columns];
        $index = new IndexDefinition($cols, $type, $name);
        $this->indexes[] = $index;
        $this->commands[] = ['type' => 'addIndex', 'index' => $index];

        return $index;
    }
}
