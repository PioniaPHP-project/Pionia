# Database migrations

Schema builder and migrator for Pionia apps. Migrations live under `database/migrations/` (or `[migrations] PATH`) and are plain PHP files that return an anonymous `Migration` instance.

## Quick start

```bash
# Create a table migration
php pionia make:table users --columns="email:string:unique,name:string" --timestamps

# Run pending migrations
php pionia migrate

# See what has run
php pionia migrate:status

# Roll back the last batch
php pionia migrate:rollback

# Drop everything and re-run (sqlite; use --force on mysql/pgsql)
php pionia migrate:fresh
```

### Other makers

| Command | Alias | Purpose |
|---------|-------|---------|
| `make:migration` | `migrate:make` | Blank or `--table` stub |
| `make:table` | `migrate:make-table` | `Schema::create` stub |
| `make:migration:column` | `migrate:add-column` | Add columns |
| `make:migration:index` | `migrate:add-index` | Add index / unique |
| `make:migration:foreign` | `migrate:add-foreign` | Add foreign id |
| `make:pivot` | `make:migration:pivot` | Many-to-many pivot table |

Column DSL: `email:email:unique,name:string,phone:phone:nullable,org_id:foreignId:orgs`

## Migration file shape

```php
<?php

use Pionia\Database\Blueprint;
use Pionia\Database\Migrations\Migration;
use Pionia\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

## Column constraints (fluent chaining)

Columns are **NOT NULL by default**. Chain modifiers in any order:

```php
$table->string('first_name')->nonNullable()->unique();
$table->string('middle_name')->nullable();
$table->email()->unique()->comment('Login identity');
$table->integer('age')->unsigned()->defaultsTo(0);
$table->string('code', 10)->required()->index()->check("{column} LIKE 'X%'");
$table->foreignId('org_id')->nonNullable()->constrained('orgs')->cascadeOnDelete();
```

| Modifier | Purpose |
|----------|---------|
| `nullable()` / `nonNullable()` / `notNull()` / `required()` | NULL vs NOT NULL |
| `unique()` / `index()` / `primary()` | Indexes / keys |
| `default($v)` / `defaultsTo($v)` | Default value |
| `unsigned()` / `signed()` | Numeric sign |
| `length($n)` / `precision($p, $s)` | Size after the type call |
| `comment($text)` | Column comment (MySQL) |
| `after($col)` | Column order (MySQL) |
| `check($sql)` / `constraint($sql)` | CHECK (`{column}` placeholder) |
| `useCurrent()` / `useCurrentOnUpdate()` | Timestamp defaults |
| `constrained()` | FK from `foreignId` |
| `change()` | Alter existing column |

## Blueprint reference

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid');
    $table->ulid('ulid');
    $table->string('title', 120);
    $table->text('body')->nullable();
    $table->integer('views')->default(0);
    $table->boolean('published')->default(false);
    $table->decimal('price', 10, 2);
    $table->json('meta')->nullable();
    $table->enum('status', ['draft', 'live']);
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['status', 'published']);
    $table->unique(['user_id', 'title']);
});

Schema::table('posts', function (Blueprint $table) {
    $table->string('slug')->after('title'); // MySQL only
    $table->dropColumn('views');
    $table->renameColumn('body', 'content');
});

Schema::dropIfExists('posts');
Schema::rename('posts', 'articles');
Schema::hasTable('users');
Schema::hasColumn('users', 'email');
Schema::raw('CREATE INDEX …');
```

### Foreign keys

```php
$table->foreign('author_id')->references('id')->on('users')->cascadeOnDelete();
$table->foreignId('org_id')->constrained('orgs')->nullOnDelete();
```

### Many-to-many (pivot tables)

```bash
php pionia make:pivot posts tags --timestamps
```

```php
Schema::create('posts', fn ($t) => $t->id() && $t->string('title'));
Schema::create('tags', fn ($t) => $t->id() && $t->string('name'));

// Creates `post_tag` with post_id, tag_id, composite PK, cascade deletes
Schema::manyToMany('posts', 'tags', function (Blueprint $table) {
    $table->string('role')->nullable(); // optional pivot data
}, timestamps: true);

Schema::dropManyToMany('posts', 'tags');
```

### Specialized fields (Django-style, with DB CHECK where useful)

```php
$table->email();              // VARCHAR(254) + email-like CHECK
$table->phone();              // VARCHAR(32) + length CHECK
$table->url('website');       // must start with http(s)://
$table->slug()->unique();     // no spaces
$table->ipAddress();
$table->macAddress();
$table->year('founded');
$table->money('price');       // DECIMAL(19,4)
$table->currency();           // CHAR-ish length 3
$table->rememberToken();
$table->morphs('taggable');   // taggable_type + taggable_id
$table->string('code')->check("{column} LIKE 'X%'"); // custom CHECK
```

These are **database soft constraints**, not a replacement for app-level validation (`#[ValidateField]`, etc.).

Drivers: SQLite, MySQL/MariaDB, PostgreSQL (via `ConnectionManager` + grammar).

## Configuration

`environment/settings.ini`:

```ini
[migrations]
PATH=database/migrations
TABLE=migrations
```

## Provider migrations

Packages can ship migrations by returning absolute directories from `Provider::migrations()`:

```php
public function migrations(): array
{
    return [dirname(__DIR__) . '/database/migrations'];
}
```

`migrate` / `migrate:status` merge the app path with every registered provider path. Duplicate basenames raise `MigrationException`.

## Testing

Use `UsesInMemoryDatabase` and register the connection:

```php
$connection = $this->useInMemoryDatabase();
connectionManager()->register('default', $connection);
```
