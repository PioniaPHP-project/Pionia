# Framework documentation

| File | Purpose |
|------|---------|
| `HELPERS.md` | Global helpers reference (`defer`, `async`, Porm, API paths, …) |
| `MOONLIGHT-DOCS.md` | Moonlight `@moonlight-*` tag reference |
| `PORM.md` | Porm query builder notes |
| `MIGRATIONS.md` | Schema builder, migrator, and maker commands |

**phpDocumentor output** belongs in `build/docs/` (generated via `composer document:framework`). The script downloads phpDocumentor **v3.9.1** into `build/tools/` on first run — do not use a global `/usr/local/bin/phpDocumentor` PHAR on PHP 8.5 (old versions flood deprecations and crash). Do not commit stale HTML under `docs/` — run `bin/clean-stale-docs` to remove legacy generated files if present.
