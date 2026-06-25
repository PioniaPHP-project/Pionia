# AGENTS.md — PioniaCore

Guidance for AI agents and contributors working in this repository.

## Repository layout

| Path | Purpose |
|------|---------|
| `src/Pionia/` | Framework core (ships on Packagist) |
| `example/` | Dev-only sample app (`autoload-dev`); **not** in release archives — see `example/README.md` |
| `tests/` | PHPUnit suite; run via `bin/test` |
| `example/environment/` | `.env`, `settings.ini`, database config |

## Bootstrap & request flow

1. `example/bootstrap/application.php` → `AppRealm::create()` boots the realm (singleton).
2. `example/bootstrap/routes.php` → registers API switches via `router($app)->switch(...)`.
3. HTTP entry: `example/public/index.php` requires `routes.php` and dispatches through `WebKernel`.

**Helpers:** `app()`, `realm()`, and `container()` return the same booted `AppRealm` instance.

## API model (switch / service / action)

- **Switch** (`Application\Switches\*`) — versioned API entry (`/api/v1/`, `/api/v2/`).
- **Service** (`Application\Services\*`) — business logic; registered in `Switch::registerServices()`.
- **Action** — method on service invoked from POST body: `{ "service": "auth", "action": "list_auth" }`.

Register a switch in `bootstrap/routes.php`:

```php
router($app)->switch(MainSwitch::class, 'v1');
```

**API URL helpers** (use these in docs, templates, and clients — not bare `apiBase()`):

| Helper | Example |
|--------|---------|
| `apiBase()` | `/api/` (prefix only, no version) |
| `defaultApiVersion()` | `v1` |
| `apiVersionPath()` | `/api/v1/` |
| `apiPingPath()` | `/api/v1/ping` |

```bash
curl -s http://127.0.0.1:8003/api/v1/ping
curl -s -X POST http://127.0.0.1:8003/api/v1/ \
  -H "Content-Type: application/json" \
  -d '{"service":"auth","action":"list_auth"}'
```

## Exception pipeline

All uncaught throwables flow through `ExceptionPipeline`:

```php
$app->exceptions()
    ->handler(App\ExceptionHandler::class)   // optional replacement
    ->reportable(fn (Throwable $e) => ...)   // Sentry, etc.
    ->dontReport(ValidationException::class)
    ->map(ResourceNotFoundException::class, fn ($e) => response(404, $e->getMessage()));
```

- `pionia_handle_exception($e, $request)` delegates to the pipeline.
- `report($e)` logs via the pipeline without rendering a response.
- Implement `Pionia\Contracts\RenderableException` (or extend `HttpException`) for self-rendering domain errors.

**Debug responses:** When `DEBUG=true` or `APP_DEBUG=true`, JSON errors include `returnData` with `exception`, `file`, `line`, and optional `trace` (controlled by `[exceptions] RENDER_TRACES` in `settings.ini`). Production hides the message as `An unexpected error occurred.`

## Logging

- Default logger: `logger()` → `LogManager` default channel → `PioniaLogger`.
- Named channel: `logger('api')`; register via `LogManager::extend()` or provider `configureLogging()`.
- Sensitive keys redacted via `[logging] HIDE_IN_LOGS` in `settings.ini`.

## Static files & welcome page

**`GET /` resolution order:**

1. `public/index.html` exists → serve user SPA entry (Vite/build output).
2. Otherwise → `FrameworkWelcomePage` (framework-owned, not overridable).

**Framework assets** live in `src/Pionia/Resources/public/` and are served at `/__pionia/{path}` (favicon, logo, `welcome.css`). Do not copy these into the user's `public/static/`.

User assets: `public/static/` via `/static/{path}`; media uploads via `/media/{path}` from `storage/media`.

## Extension points

| Hook | Where |
|------|--------|
| Routes / switches | `bootstrap/routes.php` |
| Providers | `BaseProvider`: `routes()`, `middlewares()`, `authentications()`, `configureLogging()`, `configureExceptions()` |
| Middleware / auth | Environment `settings.ini` or provider chains |
| Exception handler | `$app->exceptions()` or `error_handler` binding |

## Testing policy

- Run `bin/test` (PHPUnit on PHP 8.5+).
- **Every feature change must include tests** in the same PR.
- Test bootstrap: `tests/bootstrap.php` loads the example app with `PIONIA_TESTING` (null logger, `DEBUG=false`). Use `InteractsWithTestEnvironment::setDebugEnv()` when a test needs debug behavior.

## PHP version

Minimum **PHP 8.5**. Avoid deprecated patterns (e.g. `ReflectionMethod::setAccessible()`).

## Packagist releases

`composer.json` `archive.exclude` omits `example/` and `tests/`. Consumer apps use `Application\` namespaces in their own repos; the monorepo maps them under `autoload-dev` only.
