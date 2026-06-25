# PioniaCore example app

Dev-only sample application (`autoload-dev`). Not shipped in Packagist releases.

## Run

```bash
composer install   # from repo root
cd example
php pionia serve
```

Default URL: `http://127.0.0.1:8003/` (see `environment/.env` for `PORT`).

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

Registered services: `auth`, `category` (`switches/MainSwitch.php`).

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
