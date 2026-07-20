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

    /**
     * JSONB on PostgreSQL; falls back to JSON/TEXT on MySQL/SQLite.
     */
    public function jsonb(string $name): ColumnDefinition
    {
        return $this->addColumn('jsonb', $name);
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

    // --- Django-style / pragmatic specialized fields -----------------------------

    /**
     * Email address: VARCHAR(254) + CHECK that the value looks like an email.
     * DB-level soft validation (not a full RFC parser).
     */
    public function email(string $name = 'email'): ColumnDefinition
    {
        return $this->string($name, 254)
            ->check("{column} LIKE '%_@_%.__%'");
    }

    /**
     * Phone number: VARCHAR(32) + length/charset CHECK (digits and common punctuation).
     */
    public function phone(string $name = 'phone'): ColumnDefinition
    {
        return $this->string($name, 32)
            ->check("length({column}) BETWEEN 7 AND 32");
    }

    /**
     * URL: VARCHAR(2048) + CHECK that it starts with http:// or https://.
     */
    public function url(string $name = 'url'): ColumnDefinition
    {
        return $this->string($name, 2048)
            ->check("({column} LIKE 'http://%' OR {column} LIKE 'https://%')");
    }

    /**
     * URL-safe slug: lowercase letters, digits, hyphens; no spaces.
     */
    public function slug(string $name = 'slug'): ColumnDefinition
    {
        return $this->string($name, 255)
            ->check("length({column}) > 0 AND {column} NOT LIKE '% %'");
    }

    /**
     * IPv4 or IPv6 address storage (VARCHAR(45)).
     */
    public function ipAddress(string $name = 'ip_address'): ColumnDefinition
    {
        return $this->string($name, 45)
            ->check("length({column}) >= 7");
    }

    /**
     * MAC address: XX:XX:XX:XX:XX:XX style length.
     */
    public function macAddress(string $name = 'mac_address'): ColumnDefinition
    {
        return $this->string($name, 17)
            ->check("length({column}) = 17");
    }

    /**
     * Calendar year as a small integer with a sensible range CHECK.
     */
    public function year(string $name = 'year'): ColumnDefinition
    {
        return $this->smallInteger($name)
            ->check('{column} BETWEEN 1900 AND 2200');
    }

    /**
     * Monetary amount: DECIMAL(19, 4) by default.
     */
    public function money(string $name, int $precision = 19, int $scale = 4): ColumnDefinition
    {
        return $this->decimal($name, $precision, $scale);
    }

    /**
     * ISO-ish currency code (USD, UGX, …).
     */
    public function currency(string $name = 'currency'): ColumnDefinition
    {
        return $this->string($name, 3)
            ->check("length({column}) = 3");
    }

    /**
     * Laravel-style remember-me token column.
     */
    public function rememberToken(string $name = 'remember_token'): ColumnDefinition
    {
        return $this->string($name, 100)->nullable();
    }

    public function unsignedInteger(string $name): ColumnDefinition
    {
        return $this->integer($name)->unsigned();
    }

    public function unsignedBigInteger(string $name): ColumnDefinition
    {
        return $this->bigInteger($name)->unsigned();
    }

    /**
     * `*_type` + `*_id` morph columns for polymorphic relations (no FK).
     */
    public function morphs(string $name): void
    {
        $this->string($name . '_type');
        $this->unsignedBigInteger($name . '_id')->index();
        $this->index([$name . '_type', $name . '_id']);
    }

    public function nullableMorphs(string $name): void
    {
        $this->string($name . '_type')->nullable();
        $this->unsignedBigInteger($name . '_id')->nullable()->index();
        $this->index([$name . '_type', $name . '_id']);
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
