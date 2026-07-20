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

Column DSL: `email:string:unique,name:string,org_id:foreignId:orgs`

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
