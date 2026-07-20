# PioniaCore example app

Dev-only sample application (`autoload-dev`). Not shipped in Packagist releases.

## Run

```bash
composer install   # from repo root
cd example
php pionia serve
```

Default URL: `http://127.0.0.1:8003/` (see `environment/.env` for `PORT`).

## RoadRunner (persistent workers)

For production-like local testing with boot-once workers:

```bash
composer install   # includes spiral/roadrunner-http in dev
cd example
./../vendor/bin/rr get -l ./rr   # download RoadRunner binary once
php pionia runserver              # HTTP port from PORT/.env (8003 here) or default 8003
```

Or run the binary directly: `./rr serve -c .rr.yaml`

The `worker.php` entry bootstraps Pionia once per worker process. PDO connections are pooled via `ConnectionManager` for the lifetime of the worker.

## API

Switches are registered in `bootstrap/routes.php`. The example registers `MainSwitch` as **`v1`**, so all API traffic goes under **`/api/v1/`** — not `/api/` alone.

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/ping` | GET | Health / status |
| `/api/v1/` | POST | Service actions (`service` + `action` in JSON body) |

```bash
# Status
curl -s http://127.0.0.1:8003/api/v1/ping | jq

# Example service (AuthService via MainSwitch)
curl -s -X POST http://127.0.0.1:8003/api/v1/ \
  -H "Content-Type: application/json" \
  -d '{"service":"auth","action":"list_auth"}' | jq
```

Registered services: `auth`, `category`, `mail`, `sampolo` (`switches/MainSwitch.php`).

`sampolo` is the canonical **generic CRUD** demo (`SampoloService` → `sample_table` with company join).

## API documentation

Actions are documented with `@moonlight-*` PHPDoc tags on service classes. Generate OpenAPI + Markdown:

```bash
composer document:api          # from repo root → example/docs/api/
composer document:api:check    # CI drift check
```

Outputs: `example/docs/api/openapi.json`, `example/docs/api/index.md`, and `example/docs/api/index.html` (Scalar UI).

Runtime docs (gated by `DOCS_ENABLED` or `DEBUG=true`):

```bash
open http://127.0.0.1:8003/docs
curl -s http://127.0.0.1:8003/docs/openapi.json | jq
php example/pionia api:catalog
curl -s http://127.0.0.1:8003/api/v1/__catalog | jq
```

Optional lock (`example/environment/settings.ini`):

```ini
[docs]
ENABLED=true
TOKEN=your-docs-secret

[stats]
ENABLED=true
TOKEN=your-stats-secret
```

Then open `/docs?token=...` and `/stats?token=...` (separate tokens).

See [`docs/MOONLIGHT-DOCS.md`](../docs/MOONLIGHT-DOCS.md) for the full tag reference.

## Database (SQLite default)

Default connection is **SQLite** (`database.sqlite3` in the example root, gitignored).

```bash
cd example
php pionia migrate          # apply database/migrations/
php pionia migrate:status
```

PostgreSQL settings remain under `[db_pgsql]` in `settings.ini` if you prefer Postgres.

Connections are pooled per process via `ConnectionManager` — `db()` / `table()` reuse the same PDO until the worker exits.

## Middleware & authentication

Registered in `environment/settings.ini`:

| Key | Class | Purpose |
|-----|-------|---------|
| `request_id` | `Application\Middlewares\RequestIdMiddleware` | Adds `X-Request-Id` header |
| `demo` | `Application\Authentications\DemoAuthentication` | `Authorization: Bearer demo-token` |

```bash
curl -s http://127.0.0.1:8003/api/v1/ping -H "Authorization: Bearer demo-token"
```

## Config

- `environment/.env` — `DEBUG`, `PORT`, database URL
- `environment/settings.ini` — logging, welcome page flags, exceptions

## `public/` layout

The web root is **`example/public/`** (one level only). Do not nest another `public/` inside it.

```
example/public/
├── index.php          # HTTP entry (required)
├── index.html         # optional — your SPA; replaces the framework welcome page at GET /
├── static/            # user assets served at /static/{path}
│   ├── favicon.ico
│   └── …
└── .htaccess
```

| Path on disk | URL | Used by |
|--------------|-----|---------|
| `public/index.html` | `GET /` | Your frontend (if present) |
| `public/static/…` | `/static/…` | `DefaultRoutes::staticFilesRouter` |
| *(framework)* `src/Pionia/Resources/public/` | `/__pionia/…` | Welcome page CSS, logo (not copied here) |

A nested `public/public/` with its own `static/` is **not** part of the framework — it is usually a mistaken copy or build output path. Only `public/static/` is wired up; files under `public/public/static/` are not served unless you add custom routes.

## Tests

From the repo root: `bin/test` (loads this example app via `tests/bootstrap.php`).
