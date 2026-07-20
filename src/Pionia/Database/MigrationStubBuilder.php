<?php

namespace Pionia\Database;

use Pionia\Database\Migrations\MigrationException;
use Pionia\Utils\Filesystem;

/**
 * Shared stub writer for migration maker commands.
 *
 * Always writes timestamped files into the application migrations directory.
 */
final class MigrationStubBuilder
{
    /**
     * Parse a column DSL string into structured definitions.
     *
     * Example: `email:string:unique,name:string,org_id:foreignId:orgs`
     *
     * @return list<array{name: string, type: string, modifiers: list<string>, constrained: ?string}>
     */
    public static function parseColumns(string $dsl): array
    {
        $dsl = trim($dsl);
        if ($dsl === '') {
            return [];
        }

        $columns = [];
        foreach (explode(',', $dsl) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $bits = array_values(array_filter(array_map('trim', explode(':', $part)), fn ($b) => $b !== ''));
            if ($bits === []) {
                continue;
            }
            $name = $bits[0];
            $type = $bits[1] ?? 'string';
            $modifiers = [];
            $constrained = null;
            for ($i = 2; $i < count($bits); $i++) {
                $mod = $bits[$i];
                if (in_array($type, ['foreignId', 'foreign'], true) && !in_array(strtolower($mod), ['nullable', 'unique', 'index', 'primary'], true)) {
                    $constrained = $mod;
                } else {
                    $modifiers[] = $mod;
                }
            }
            $columns[] = [
                'name' => $name,
                'type' => $type,
                'modifiers' => $modifiers,
                'constrained' => $constrained,
            ];
        }

        return $columns;
    }

    /**
     * @param list<array{name: string, type: string, modifiers: list<string>, constrained: ?string}> $columns
     */
    public static function writeCreateTable(
        string $table,
        array $columns = [],
        bool $timestamps = true,
        bool $softDeletes = false,
        ?string $name = null,
    ): string {
        $body = self::indent("Schema::create('{$table}', function (Blueprint \$table) {");
        $body .= "\n" . self::indent("\$table->id();", 2);

        foreach ($columns as $column) {
            $body .= "\n" . self::indent(self::columnLine($column), 2);
        }

        if ($timestamps) {
            $body .= "\n" . self::indent("\$table->timestamps();", 2);
        }
        if ($softDeletes) {
            $body .= "\n" . self::indent("\$table->softDeletes();", 2);
        }

        $body .= "\n" . self::indent('});');

        $down = self::indent("Schema::dropIfExists('{$table}');");

        return self::write($name ?? ('create_' . $table . '_table'), $body, $down);
    }

    /**
     * @param list<array{name: string, type: string, modifiers: list<string>, constrained: ?string}> $columns
     */
    public static function writeAddColumns(string $table, array $columns, ?string $name = null): string
    {
        $upLines = [];
        $downCols = [];
        foreach ($columns as $column) {
            $upLines[] = self::columnLine($column);
            $downCols[] = "'" . $column['name'] . "'";
        }

        $up = self::indent("Schema::table('{$table}', function (Blueprint \$table) {");
        foreach ($upLines as $line) {
            $up .= "\n" . self::indent($line, 2);
        }
        $up .= "\n" . self::indent('});');

        $down = self::indent("Schema::table('{$table}', function (Blueprint \$table) {");
        $down .= "\n" . self::indent('$table->dropColumn([' . implode(', ', $downCols) . ']);', 2);
        $down .= "\n" . self::indent('});');

        return self::write($name ?? ('add_columns_to_' . $table . '_table'), $up, $down);
    }

    /**
     * @param list<string> $columns
     */
    public static function writeAddIndex(
        string $table,
        array $columns,
        string $type = 'index',
        ?string $indexName = null,
        ?string $name = null,
    ): string {
        $cols = implode(', ', array_map(fn (string $c) => "'" . $c . "'", $columns));
        $method = $type === 'unique' ? 'unique' : 'index';
        $nameArg = $indexName !== null ? ", '{$indexName}'" : '';

        $up = self::indent("Schema::table('{$table}', function (Blueprint \$table) {");
        $up .= "\n" . self::indent("\$table->{$method}([{$cols}]{$nameArg});", 2);
        $up .= "\n" . self::indent('});');

        $dropMethod = $type === 'unique' ? 'dropUnique' : 'dropIndex';
        $resolvedName = $indexName ?? ($table . '_' . implode('_', $columns) . '_' . $method);

        $down = self::indent("Schema::table('{$table}', function (Blueprint \$table) {");
        $down .= "\n" . self::indent("\$table->{$dropMethod}('{$resolvedName}');", 2);
        $down .= "\n" . self::indent('});');

        return self::write($name ?? ("add_{$method}_to_{$table}_table"), $up, $down);
    }

    public static function writeAddForeign(
        string $table,
        string $column,
        string $references = 'id',
        ?string $on = null,
        bool $cascadeOnDelete = false,
        ?string $name = null,
    ): string {
        $on ??= self::guessTableFromForeignColumn($column);
        $cascade = $cascadeOnDelete ? '->cascadeOnDelete()' : '';

        $up = self::indent("Schema::table('{$table}', function (Blueprint \$table) {");
        $up .= "\n" . self::indent("\$table->foreignId('{$column}')->constrained('{$on}'){$cascade};", 2);
        $up .= "\n" . self::indent('});');

        $fkName = $table . '_' . $column . '_foreign';
        $down = self::indent("Schema::table('{$table}', function (Blueprint \$table) {");
        $down .= "\n" . self::indent("\$table->dropForeign('{$fkName}');", 2);
        $down .= "\n" . self::indent("\$table->dropColumn('{$column}');", 2);
        $down .= "\n" . self::indent('});');

        return self::write($name ?? ("add_{$column}_foreign_to_{$table}_table"), $up, $down);
    }

    /**
     * Scaffold a many-to-many pivot migration using {@see Schema::manyToMany()}.
     */
    public static function writeManyToMany(
        string $leftTable,
        string $rightTable,
        bool $timestamps = false,
        ?string $pivot = null,
        ?string $name = null,
    ): string {
        $pivotArg = $pivot !== null ? ", table: '{$pivot}'" : '';
        $ts = $timestamps ? 'true' : 'false';

        $up = self::indent("Schema::manyToMany('{$leftTable}', '{$rightTable}', timestamps: {$ts}{$pivotArg});");
        $down = self::indent("Schema::dropManyToMany('{$leftTable}', '{$rightTable}'" . ($pivot !== null ? ", '{$pivot}'" : '') . ');');

        $basename = $name ?? ('create_' . ($pivot ?? ($leftTable . '_' . $rightTable . '_pivot')) . '_table');

        return self::write($basename, $up, $down);
    }

    public static function writeBlank(?string $name = null): string
    {
        $up = self::indent('// Schema::create(…);');
        $down = self::indent('// Schema::dropIfExists(…);');

        return self::write($name ?? 'blank_migration', $up, $down);
    }

    public static function migrationsDirectory(): string
    {
        try {
            $dir = directoryPath(\DIRECTORIES::MIGRATIONS_DIR->name);
        } catch (\Throwable) {
            $base = defined('BASE_PATH') ? (string) BASE_PATH : getcwd();
            $dir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        }

        $fs = new Filesystem();
        if (!$fs->exists($dir)) {
            $fs->mkdir($dir);
        }

        return $dir;
    }

    public static function write(string $basename, string $upBody, string $downBody): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]+/', '_', $basename) ?: 'migration';
        $safe = trim($safe, '_');
        $filename = date('Y_m_d_His') . '_' . $safe . '.php';
        $path = self::migrationsDirectory() . DIRECTORY_SEPARATOR . $filename;

        if (is_file($path)) {
            usleep(1_000_000);
            $filename = date('Y_m_d_His') . '_' . $safe . '.php';
            $path = self::migrationsDirectory() . DIRECTORY_SEPARATOR . $filename;
        }

        $contents = <<<PHP
<?php

use Pionia\Database\Blueprint;
use Pionia\Database\Migrations\Migration;
use Pionia\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
{$upBody}
    }

    public function down(): void
    {
{$downBody}
    }
};

PHP;

        $written = file_put_contents($path, $contents);
        if ($written === false) {
            throw new MigrationException("Unable to write migration stub to [{$path}].");
        }

        return $path;
    }

    /**
     * @param array{name: string, type: string, modifiers: list<string>, constrained: ?string} $column
     */
    private static function columnLine(array $column): string
    {
        $name = $column['name'];
        $type = $column['type'];
        $line = match ($type) {
            'id' => "\$table->id('{$name}')",
            'uuid' => "\$table->uuid('{$name}')",
            'ulid' => "\$table->ulid('{$name}')",
            'email' => "\$table->email('{$name}')",
            'phone' => "\$table->phone('{$name}')",
            'url' => "\$table->url('{$name}')",
            'slug' => "\$table->slug('{$name}')",
            'ip', 'ipAddress', 'ip_address' => "\$table->ipAddress('{$name}')",
            'mac', 'macAddress', 'mac_address' => "\$table->macAddress('{$name}')",
            'year' => "\$table->year('{$name}')",
            'money' => "\$table->money('{$name}')",
            'currency' => "\$table->currency('{$name}')",
            'text' => "\$table->text('{$name}')",
            'integer' => "\$table->integer('{$name}')",
            'bigInteger' => "\$table->bigInteger('{$name}')",
            'smallInteger' => "\$table->smallInteger('{$name}')",
            'tinyInteger' => "\$table->tinyInteger('{$name}')",
            'boolean' => "\$table->boolean('{$name}')",
            'float' => "\$table->float('{$name}')",
            'double' => "\$table->double('{$name}')",
            'decimal' => "\$table->decimal('{$name}')",
            'date' => "\$table->date('{$name}')",
            'dateTime' => "\$table->dateTime('{$name}')",
            'timestamp' => "\$table->timestamp('{$name}')",
            'json' => "\$table->json('{$name}')",
            'jsonb' => "\$table->jsonb('{$name}')",
            'binary' => "\$table->binary('{$name}')",
            'foreignId', 'foreign' => "\$table->foreignId('{$name}')",
            default => "\$table->string('{$name}')",
        };

        foreach ($column['modifiers'] as $modifier) {
            $mod = strtolower($modifier);
            $line .= match ($mod) {
                'nullable' => '->nullable()',
                'unique' => '->unique()',
                'index' => '->index()',
                'primary' => '->primary()',
                default => '',
            };
        }

        if ($column['constrained'] !== null) {
            $line .= "->constrained('{$column['constrained']}')";
        } elseif (in_array($type, ['foreignId', 'foreign'], true)) {
            $line .= '->constrained()';
        }

        return $line . ';';
    }

    private static function indent(string $line, int $levels = 1): string
    {
        return str_repeat('    ', $levels) . $line;
    }

    private static function guessTableFromForeignColumn(string $column): string
    {
        $base = str_ends_with($column, '_id') ? substr($column, 0, -3) : $column;
        if (function_exists('plural')) {
            return (string) plural($base);
        }

        return $base . 's';
    }
}
