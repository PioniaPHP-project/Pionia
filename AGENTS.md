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

## HTTP request lifecycle (Phase 4)

Split **boot** from **handle** for FPM today and RoadRunner workers later:

| Method | Role |
|--------|------|
| `WebApplication::bootOnce()` | Run `powerUp()` once per process (`$booted` guard) |
| `WebApplication::handleRequest(Request)` | Match route + return `Response` (no `send()`) |
| `WebApplication::fly()` | `createFromGlobals()` → `handleRequest()` → `send()` (FPM entry) |
| `WebApplication::resetBetweenRequests()` | Flush per-request hooks (worker mode) |

`WebKernel::terminate()` only **prepares** the response; the caller sends it. In tests use `handleRequest()` or `MakesHttpRequests` traits.

`runtimeMode()` — `fpm` \| `cli` \| `worker` \| `testing` (`PIONIA_TESTING` / `PIONIA_RUNTIME`).

`renderToString()` renders without `exit()`; `render()` exits only outside worker/testing modes.

## Database connections

`ConnectionManager` pools PDO instances per process. `Connection::connect('default')` reuses the pool; `Connection::open()` bypasses it (tests, one-off configs).

```php
connectionManager()->connection('default');
connectionManager()->disconnect(); // worker shutdown only — not per HTTP request
```

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
| `apiCatalogPath()` | `/api/v1/__catalog` (JSON catalog, debug only) |
| `/docs` | Interactive Scalar UI (`DOCS_ENABLED` or `DEBUG`) |
| `/stats` | Developer health dashboard (`STATS_ENABLED` or `DEBUG`) |

```bash
curl -s http://127.0.0.1:8003/api/v1/ping
curl -s -X POST http://127.0.0.1:8003/api/v1/ \
  -H "Content-Type: application/json" \
  -d '{"service":"auth","action":"list_auth"}'
```

## API documentation (Moonlight)

Document actions with `@moonlight-*` PHPDoc or `#[MoonlightAction]`. Full reference: [`docs/MOONLIGHT-DOCS.md`](docs/MOONLIGHT-DOCS.md).

```bash
composer document:api          # example/docs/api/openapi.json + index.md + index.html
composer document:api:check    # CI drift check
php example/pionia api:catalog # JSON catalog (stdout)
open http://127.0.0.1:8003/docs  # when DOCS_ENABLED or DEBUG
curl -s http://127.0.0.1:8003/api/v1/__catalog  # JSON catalog (same gate)
```

**Runtime docs gate** (`example/environment/settings.ini` or `.env`):

| Setting | Purpose |
|---------|---------|
| `DOCS_ENABLED=true` | Expose `/docs` when `DEBUG=false` (e.g. staging) |
| `DOCS_TOKEN=secret` | Require `?token=secret` or `X-Docs-Token` header |
| `STATS_ENABLED=true` | Expose `/stats` when `DEBUG=false` |
| `STATS_TOKEN=secret` | Separate token for stats (`X-Stats-Token`) |
| *(unset)* | Docs/stats follow `DEBUG` (default dev behaviour) |

| Tag | Purpose |
|-----|---------|
| `@moonlight-service` | Service alias (class level) |
| `@moonlight-action` | Action name |
| `@moonlight-summary` | One-line description |
| `@moonlight-param type name Description` | Request body field |
| `@moonlight-example {...}` | Example JSON payload |

Framework internals: `composer document:framework` → `build/docs/` (phpDocumentor). Moonlight covers the `{ service, action }` API.

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

### Composer scripts

| Script | Purpose |
|--------|---------|
| `composer test` | Full suite |
| `composer test:unit` | Unit tests only |
| `composer test:feature` | HTTP integration (`tests/Feature/`) |
| `composer test:console` | CLI smoke tests |
| `composer test:coverage` | Text + Clover report (`build/coverage.xml`) |
| `composer test:ci` | CI entry (coverage Clover) |

CI (`.github/workflows/tests.yml`) runs on PHP 8.5 with **pcov** and enforces **50%** line coverage on `src/Pionia/` via `bin/coverage-check`.

### `Pionia\TestSuite` traits

Extend `Pionia\TestSuite\PioniaTestCase` (includes all traits below):

| Trait | Use |
|-------|-----|
| `CreatesApplication` | `application()`, `webApplication()` |
| `MakesHttpRequests` | `get()`, `postApi()`, `getApiPing()` → `TestResponse` |
| `AssertsPioniaResponses` | `assertPioniaOk()`, `assertPioniaError()`, `assertJsonStructure()` |
| `UsesInMemoryDatabase` | `useInMemoryDatabase()` for Porm tests |
| `InteractsWithConsole` | `artisan('list')`, `assertExitCode(0)` |
| `InteractsWithTestEnvironment` | Toggle `DEBUG` per test |

Example feature test:

```php
$response = $this->getApiPing();
$this->assertPioniaOk($response);
$this->postApi('auth', 'list_auth');
```

## PHP version

Minimum **PHP 8.5**. Avoid deprecated patterns (e.g. `ReflectionMethod::setAccessible()`).

### PHP 8.5 features in use

| Feature | Where |
|---------|--------|
| `#[\NoDiscard]` | `response()`, `app()`, `table()`, API path helpers |
| `\|>` pipe operator | `WebKernel::prepareRequest()` |
| `array_find` / `array_any` | `Arrayable::find()`, `Arrayable::any()` |
| `get_exception_handler()` | Tests assert handlers registered at boot |
| `renderToString()` | Preferred over deprecated `render()` |
| `RuntimeMode` | FPM / CLI / worker / testing lifecycle |

Centralized path safety: `Pionia\Utils\SafePath::resolveFileWithinBase()` (static/media routers).

## Packagist releases

`composer.json` `archive.exclude` omits `example/` and `tests/`. Consumer apps use `Application\` namespaces in their own repos; the monorepo maps them under `autoload-dev` only.
