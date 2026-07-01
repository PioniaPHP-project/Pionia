# Porm — database layer reference

Porm (`Pionia\Porm\`) ships inside `pionia/pionia-core`. Full prose guides live in [pionia-docs](https://pionia.netlify.app/documentation/database/) (`content/docs/documentation/database/`).

## Entry points

```php
table('users');                    // Porm on default connection
table('users', 'u', 'db_pgsql');   // aliased + named connection
connectionManager()->connection('default');
```

## Query modes

| Start | Type | Terminal methods |
|-------|------|------------------|
| `table('t')` | `Porm` | `get`, `all`, `save`, `update`, `delete`, `has`, `random`, `chunk`, aggregates, `raw`, `inTransaction` |
| `->filter([...])` | `Builder` | `all`, `get`, `first`, `count`, `orderBy`, `limit`, `startAt` |
| `->join()` | `Join` | `inner`/`left`/`right`/`full`, then `all`, `count`, `random` |

Do not mix table-level writes with an active `filter()` / `join()` chain.

### Fluent where

```php
table('users')->filter()
    ->where('username', 'jet')
    ->where('age', '>', 18)
    ->whereStartsWith('name', 'J')
    ->orWhere('email', 'jet@example.com')
    ->all();
```

Operators: `equals`, `not_equal`, `starts_with`, `ends_with`, `includes`, `in`, `between`, `is_null`, … See pionia-docs filtering guide.

## Key classes

| Class | Path |
|-------|------|
| `Porm` | `src/Pionia/Porm/Core/Porm.php` |
| `Piql` | `src/Pionia/Porm/Core/Piql.php` |
| `Builder` | `src/Pionia/Porm/Database/Builders/Builder.php` |
| `Join` | `src/Pionia/Porm/Database/Builders/Join.php` |
| `JoinOn` | `src/Pionia/Porm/Database/Builders/JoinOn.php` — ON/USING helpers |
| `JoinLoader` | `src/Pionia/Porm/Database/Builders/JoinLoader.php` — manual eager-load |
| `Where` | `src/Pionia/Porm/Database/Builders/Where.php` |
| `WhereExpression` | `src/Pionia/Porm/Database/Builders/WhereExpression.php` — fluent operator compiler |
| `Agg` | `src/Pionia/Porm/Database/Aggregation/Agg.php` |
| `PaginationCore` | `src/Pionia/Porm/PaginationCore.php` |
| `ConnectionManager` | `src/Pionia/Porm/ConnectionManager.php` |
| `Connection` | `src/Pionia/Porm/Driver/Connection.php` |

## Configuration

`environment/settings.ini` — sections with `database_type` + `database_name`; one section `default = 1`.

## GenericService integration

`GenericService` + `CrudContract` / `JoinContract` call `query()` → `table($this->table, $this->baseAlias, $this->connection)`.

Notable service properties: `$baseAlias`, `$maxListRows`, `$allowClientColumns`, `$allowClientFilters`, `$sortableColumns`, `$approximatePagination`, `$cacheListTtl`, `$cacheRetrieveTtl`, `$skipUpdatePrefetch`.

`ValidationException` (HTTP 422) is thrown for missing required fields in create/update.

## Security

- Never pass user input into `Piql::raw()` or string ON clauses — use bound parameters and `where()` / `JoinOn::map()`.
- `[Object]` column casts call `unserialize()` only when `PORM_ALLOW_OBJECT_CAST=true` or `allow_object_cast=1` on the connection.
- Increment operators (`score[+]` => 3) bind values as prepared parameters.

## Tests

```php
Connection::open(['type' => 'sqlite', 'database' => ':memory:']);
connectionManager()->register('default', $conn);
```

Suite: `tests/PormTests/`, `tests/Feature/GenericServiceCrudTest.php`.

## Doc map (pionia-docs)

| Page | Topic |
|------|-------|
| [Overview](https://pionia.netlify.app/documentation/database/) | Guide index |
| [Getting started](https://pionia.netlify.app/documentation/database/configuration-getting-started/) | Config |
| [Making queries](https://pionia.netlify.app/documentation/database/making-queries/) | CRUD |
| [Filtering](https://pionia.netlify.app/documentation/database/queries-with-filtering/) | Builder |
| [WHERE DSL](https://pionia.netlify.app/documentation/database/where-dsl/) | WHERE operators |
| [Relationships](https://pionia.netlify.app/documentation/database/relationships/) | Joins |
| [Aggregation](https://pionia.netlify.app/documentation/database/using-functions-aggregation/) | Aggregates / Agg |
| [Pagination](https://pionia.netlify.app/documentation/database/pagination/) | PaginationCore |
| [Connections](https://pionia.netlify.app/documentation/database/connections/) | Pooling |
| [Transactions & raw SQL](https://pionia.netlify.app/documentation/database/transactions-and-raw-sql/) | Transactions |
| [Performance](https://pionia.netlify.app/documentation/database/performance/) | chunk, random, explain |
| [API reference](https://pionia.netlify.app/documentation/database/api-reference/) | Method cheat sheet |

Framework guides (pionia-docs): [Exceptions](/documentation/exceptions/) · [Background work](/documentation/background-work/) · [Caching](/documentation/caching-in-pionia/) · [Maintenance](/documentation/maintenance/) · [Developer stats](/documentation/developer-stats/).
