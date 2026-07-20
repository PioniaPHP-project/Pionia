<?php

namespace Pionia\Database\Migrations;

/**
 * Base class for database migrations.
 *
 * Migration files should `return new class extends Migration { … }`.
 */
abstract class Migration
{
    /**
     * Optional connection name (defaults to the migrator's connection).
     */
    protected ?string $connection = null;

    abstract public function up(): void;

    abstract public function down(): void;

    public function getConnection(): ?string
    {
        return $this->connection;
    }

    public function setConnection(?string $connection): void
    {
        $this->connection = $connection;
    }
}
