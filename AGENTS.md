# AGENTS.md — PioniaCore

Guidance for AI agents and contributors working in this repository.

## Repository layout

| Path | Purpose |
|------|---------|
| `src/Pionia/` | Framework core (ships on Packagist) |
| `example/` | Dev-only sample app (`autoload-dev`); **not** in release archives — see `example/README.md` |
| `tests/` | PHPUnit suite; run via `bin/test` |
| `example/environment/` | `.env`, `settings.ini`, database config |

## Related repositories (v3)

| Repo | Packagist | Role |
|------|-----------|------|
| [PioniaCore](https://github.com/PioniaPHP-project/PioniaCore) | `pionia/pionia-core` | Framework library (this repo) |
| [Pionia-App](https://github.com/PioniaPHP-project/Pionia-App) | `pionia/pionia-app` | Application template (`pionia new` output) |
| [pionia-docs](https://github.com/PioniaPHP-project/pionia-docs) | — | Hugo guides at [pionia.netlify.app](https://pionia.netlify.app) |

Local paths (monorepo): `../JetFramework` (app template), `../pionia-docs`.

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
| `WebApplication::handleRequest(Request)` | Match route + return `Response` (no `send()`); auto `resetBetweenRequests()` in worker mode |
| `WebApplication::fly()` | `createFromGlobals()` → `handleRequest()` → `send()` (FPM entry) |
| `WebApplication::resetBetweenRequests()` | Flush per-request hooks (worker mode) |

**RoadRunner (Phase 5):**

```bash
composer require spiral/roadrunner-http nyholm/psr7   # in your app
./rr get -l ./rr                                     # download binary once
php example/pionia runserver                          # foreground (alias: roadrunner, rr:serve)
php example/pionia runserver --detach                 # background; logs to storage/logs/roadrunner.log
php example/pionia runserver:logs                     # tail logs in real time (Ctrl+C to stop)
php example/pionia stopserver                         # stop RoadRunner (alias: serve:rr:stop)
php example/pionia maintenance:on                     # HTTP 503 for visitors (alias: down)
php example/pionia maintenance:off                    # back to normal (alias: up)
```

- Worker entry: `example/worker.php` (boot once, PSR-7 loop)
- Config: `example/.rr.yaml` — HTTP listen port resolves as **CLI `--port`** → **`PORT` / `SERVER_PORT` in `.env`** → **`[roadrunner]` or `[server]` in `settings.ini`** → **`.rr.yaml` `http.address`** → **default `8003`**. `runserver` passes `-o http.address=…` when the resolved address differs from the file.
- `ConnectionManager` keeps PDO alive across requests; `disconnect()` on worker shutdown only
- Built-in dev server: `php pionia serve` (PHP `-S`, no RoadRunner)

`WebKernel::terminate()` only **prepares** the response; the caller sends it. In tests use `handleRequest()` or `MakesHttpRequests` traits.

`runtimeMode()` — `fpm` \| `cli` \| `worker` \| `testing` (`PIONIA_TESTING` / `PIONIA_RUNTIME`).

`renderToString()` renders without `exit()`; `render()` exits only outside worker/testing modes.

## Database connections

Porm (`table()`, `connectionManager()`) — see [`docs/PORM.md`](docs/PORM.md) and the [Porm guides](https://pionia.netlify.app/documentation/database/) in pionia-docs.

**Raw SQL:** Never pass user input into `Piql::raw()` or string ON clauses — use bound parameters and `where()` / `JoinOn::map()`. **`[Object]` column casts** call `unserialize()` only when `PORM_ALLOW_OBJECT_CAST=true` or `[db] allow_object_cast=1` in settings.

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

### Naming conventions (v3)

Framework base classes use **short names** — no `Base*` prefix. App classes use descriptive names (`MainSwitch`, `JwtAuthentication`, `RequestIdMiddleware`).

| Layer | Extend / implement | App example | CLI |
|-------|-------------------|-------------|-----|
| Switch | `Pionia\Http\Switches\ApiSwitch` | `MainSwitch` | `make:switch` |
| Service | `Pionia\Http\Services\Service` | `AuthService` | `make:service` |
| Moonlight envelope | `Pionia\Http\Response\ApiResponse` | `response()` helper | — |
| HTTP response | `Pionia\Http\Response\Response` | `Response::fromEnvelope()` | — |
| Authentication | `Pionia\Auth\Authentication` | `JwtAuthentication` | `make:auth` |
| Middleware | `Pionia\Middlewares\Middleware` | `RequestIdMiddleware` | `make:middleware` |
| Command | `Pionia\Console\Command` | `SyncOrdersCommand` | `make:command` |
| Provider | `Pionia\Base\Provider\Provider` | `AppProvider` | `make:provider` |

`ApiSwitch` is named explicitly because `Switch` is a PHP reserved keyword. The contract is `Pionia\Contracts\SwitchContract`.

Empty app directories (`middlewares/`, `authentications/`, `commands/`, `providers/`) are **not** scaffolded — `make:*` creates them on first use.

**API URL helpers**

| Helper | Example |
|--------|---------|
| `apiBase()` | `/api/` (prefix only, no version) |
| `defaultApiVersion()` | `v1` |
| `apiVersionPath()` | `/api/v1/` |
| `apiPingPath()` | `/api/v1/ping` |
| `apiCatalogPath()` | `/api/v1/__catalog` (JSON catalog, debug only) |
| `/docs` | Interactive Scalar UI (`DOCS_ENABLED` or `DEBUG`) |
| `/stats` | Developer health dashboard (`STATS_ENABLED` or `DEBUG`) |
| `/stats.json` | Same data as JSON (`STATS_TOKEN` / `X-Stats-Token`) |

```bash
curl -s http://127.0.0.1:8003/api/v1/ping
curl -s -X POST http://127.0.0.1:8003/api/v1/ \
  -H "Content-Type: application/json" \
  -d '{"service":"auth","action":"list_auth"}'
php example/pionia stats:view          # terminal request metrics (aliases: stats, viewstats)
php example/pionia stats:view --json   # JSON snapshot
php example/pionia stats:view --reset  # clear storage/metrics/requests.jsonl
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
| `[metrics] ENABLED=false` | Disable request metrics writes (stats page still works) |
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

## Caching (PSR-16)

Pionia ships a native cache layer (`PioniaCache` + `CacheManager`). All stores implement `Pionia\Cache\Contracts\CacheAdapterInterface` (extends PSR-16).

### Built-in stores

| Store | `STORE` value | Best for |
|-------|---------------|----------|
| Filesystem | `filesystem` (default) | Single server, no extra extensions |
| Array | `array` | Tests, scripts, per-worker memory |
| Null | `null` | Disable caching without code changes |
| Database | `database` | Shared cache via existing PDO connection |
| APCu | `apcu` | RoadRunner / FPM workers on one host (`ext-apcu`) |
| Redis | `redis` | Production clusters (`ext-redis`) |

Aliases: `file`, `memory`, `void`, `db`.

### Choose a store (`environment/settings.ini`)

```ini
[cache]
STORE=filesystem
TTL=3600

; Per-store options — use a section named cache_<store> or cache.stores.<store>
[cache_redis]
host=127.0.0.1
port=6379
prefix=pionia:
password=
database=0

[cache_database]
connection=default
table=cache

[cache_apcu]
prefix=pionia_

[cache_filesystem]
path=storage/cache
```

Or set `STORE=array` / `STORE=null` for local dev and CI.

### Use the cache in code

```php
app()->cacheInstance()->set('rates', $rates, 300);
$value = app()->cacheInstance()->get('rates');

// Named store (custom or built-in)
app()->cache()->store('redis')->set('session:1', $payload, 900);
```

Framework internals (providers, routes, templates) use the default store from `[cache] STORE`.

### Cache CLI

| Command | Action |
|---------|--------|
| `cache:clear` | Wipe the active store |
| `cache:prune` | Remove expired entries (filesystem, database, array) |
| `cache:delete {key}` | Delete one key |

### Application maintenance mode

Put the app behind a 503 gate for all HTTP routes except `/__pionia/*` assets.

| Command | Alias | Action |
|---------|-------|--------|
| `maintenance:on` | `down` | Enable maintenance (`[maintenance] ENABLED=true` in `environment/settings.ini`) |
| `maintenance:off` | `up` | Disable maintenance |

```bash
php example/pionia maintenance:on --message="Deploying" --retry-after=300 --bypass=secret
php example/pionia maintenance:off
```

RoadRunner workers re-read `settings.ini` on each request (no restart required). Bypass with `?bypass=secret` or `X-Maintenance-Bypass` header. You can also configure `[maintenance]` statically in `settings.ini` or via `MAINTENANCE_MODE=true` in `.env`.

### Register a custom store (application)

In a `Provider` subclass:

```php
public function configureCaching(\Pionia\Cache\CacheManager $cache): void
{
    $cache->extend('memcached', function ($app, array $config) {
        return new MemcachedCacheAdapter(
            servers: $config['servers'] ?? ['127.0.0.1:11211'],
            prefix: $config['prefix'] ?? 'pionia_',
        );
    });
}
```

Then `[cache] STORE=memcached` and `[cache_memcached]` (or `cache.stores.memcached`) for options.

Replace the default adapter entirely at bootstrap:

```php
$app->withCacheAdaptor(fn ($app, $env) => new MyCacheAdapter());
```

### Write an adapter (for apps or core contributions)

1. Implement `CacheAdapterInterface` (all PSR-16 methods; keep `$key` untyped for `psr/simple-cache` v1).
2. Optionally implement `PrunableCacheAdapterInterface` if the backend supports TTL cleanup.
3. Reuse `Pionia\Cache\Concerns\ValidatesCacheKeys` and `ImplementsBulkCacheOperations` for key rules and bulk ops.
4. Throw `Pionia\Cache\InvalidCacheArgumentException` for bad keys (reserved chars: `{}()/\@:`).
5. Register via `CacheManager::extend()` in dev; open a PR to add built-in stores under `src/Pionia/Cache/Adapters/` and wire them in `CacheManager::buildStore()`.

**Adopting community adapters into core:** prefer zero Composer deps, optional PHP extensions with clear errors, tests under `tests/Cache/`, and INI config documented above. Suggest optional extensions in `composer.json` `suggest` (e.g. `ext-redis`, `ext-apcu`).

## Console (native CLI)

Pionia ships a native console (`Pionia\Console\Application`) — no `symfony/console` in framework `require`.

### Commands

- Extend `Pionia\Console\Command` and implement `handle(): int`.
- Register args/options via `getArguments()` / `getOptions()` tuple arrays, or a `$signature` string (parsed by `Parser`).
- Styled output: `$this->info()`, `error()`, `warn()`, `table()`, `ask()`, `confirm()`, `choice()`, `withProgressBar()`.
- Colors use `<info>`, `<comment>`, `<error>`, `<warning>` tags; disable with `--no-ansi`.

### Interactive shell

```bash
php pionia shell    # aliases: tinker, repl
```

REPL with `app()`, `realm()`, `env()`, `logger()`, `cache()` available. Meta commands: `help`, `exit`, `clear`, `:history`.

### Generate custom commands

`make:command` scaffolds commands using `Pionia\Console\Input\InputArgument` / `InputOption`.

## Static files & welcome page

**`GET /` resolution order:**

1. `public/index.html` exists → serve user SPA entry (Vite/build output).
2. Otherwise → `FrameworkWelcomePage` (framework-owned, not overridable).

**Framework assets** live in `src/Pionia/Resources/public/` and are served at `/__pionia/{path}` (favicon, logo, `welcome.css`). Do not copy these into the user's `public/static/`.

User assets: `public/static/` via `/static/{path}`; media uploads via `/media/{path}` from `storage/media`.

### Vite full-stack frontend (Phase 7)

Scaffold, develop, and deploy a Vite SPA alongside the Moonlight API:

| Command | Action |
|---------|--------|
| `frontend:scaffold` | Create Vite app in `frontend/` (React, Vue, …) |
| `frontend:dev` | Vite dev server with `/api` proxy to Pionia |
| `frontend:build` | Build and copy `dist/` → `public/` |
| `frontend:clean` | Remove deployed assets from `public/` (keeps `public/static/`) |
| `frontend:drop` | Delete `frontend/` and `[frontend]` settings |

Configure via `[frontend]` in `environment/settings.ini`. When `SPA_FALLBACK=true` or `public/index.html` exists, client routes fall back to the SPA shell.

```bash
php pionia frontend:scaffold --framework=react-ts --yes
php pionia serve          # terminal 1 — API on PORT
php pionia frontend:dev   # terminal 2 — Vite on :5173
php pionia frontend:build # production — serves from public/
```

### New application scaffold (Phase 8)

**New machine (Composer only):**

```bash
composer create-project pionia/pionia-app my-app
cd my-app && composer run serve
```

**From an existing Pionia install** (or PioniaCore dev tree):

```bash
php pionia new my-app --install
php pionia new my-app --install --with-frontend=react-ts
# PioniaCore monorepo: php example/pionia new my-app --install
```

Creates bootstrap, environment, services, switches, and `composer.json` from core stubs (`src/Pionia/Resources/scaffolds/app/`) — same output as `pionia/pionia-app` on Packagist.

### Moonlight async & realtime (Phase 9)

All transports share the same `{ service, action, ...params }` envelope:

| Transport | Entry |
|-----------|--------|
| HTTP | `POST /api/v1/` (existing) |
| Programmatic | `moonlight()->dispatch('auth', 'list_auth')` |
| Async jobs | `moonlight()->async('reports', 'generate', ['month' => '01'])` |
| WebSocket RPC | Centrifugo frame → `MoonlightFrameHandler` |

**RoadRunner Jobs** — enable in `settings.ini`:

```ini
[jobs]
ENABLED = true
PIPELINE = moonlight
RPC = tcp://127.0.0.1:6001
```

`.rr.yaml` needs `rpc` + `jobs` sections (see `example/.rr.yaml`). `worker.php` uses `PioniaWorker`, which routes by `RR_MODE` to HTTP, job consumer, or Centrifuge handler.

When jobs are enabled and RR is running, `moonlight()->async()` returns `returnCode: 202` with `job_id`. In tests (`PIONIA_TESTING`), jobs run synchronously unless `PIONIA_JOBS_QUEUE=1`.

**WebSockets** — optional Centrifugo via RoadRunner `centrifuge` plugin. Install `roadrunner-php/centrifugo`, uncomment `centrifuge` in `.rr.yaml`, set `[realtime] ENABLED=true`. RPC frames use the same Moonlight envelope; responses match the HTTP JSON shape.

Key classes: `MoonlightDispatcher`, `MoonlightJobQueue`, `MoonlightJobConsumer`, `MoonlightFrameHandler`, `PioniaWorker`, `RealtimeGateway`.

### Background work (`defer()` / `async()`)

Requires `react/promise` in apps. **Prefer `defer()`** for post-response closures; use `async()` for promises or Moonlight job strings.

```php
// Closure — after HTTP response (not a new thread; same PHP worker)
defer(function () use ($user) {
    logger()->info('Sending welcome', ['email' => $user->email]);
});

// Promise chaining when needed
async(function () use ($user) {
    logger()->info('Sending welcome', ['email' => $user->email]);
})->catch(fn (\Throwable $e) => logger()->error($e->getMessage()));

// Moonlight job — queued on flush when RoadRunner Jobs is enabled
async('mail', 'send_welcome', ['email' => $user->email]);
```

| Form | When it runs | Best for |
|------|----------------|----------|
| `defer(Closure)` | After `respond()` / `send()` | Quick post-response work (webhooks, logging) |
| `async(Closure)` | Same as `defer()` | When you need `.then()` / `await()` |
| `async('service', 'action', $payload)` | RR Jobs worker when queue available; else sync after response | Email, reports, durable work |

**`async()` vs `moonlight()->async()`** — global `async()` returns a `PromiseInterface` and defers until after the response. `moonlight()->async()` returns an HTTP-shaped `202` immediately when the queue accepts the job (API-style).

**Running without RoadRunner** (`php pionia serve`, nginx/FPM):

- **`defer()`** / closure **`async()`** run after `send()`; FPM uses `fastcgi_finish_request()`; built-in server flushes output and sets `Connection: close`.
- String **`async()`** falls back to `dispatchSync()` on flush — request still succeeds; work runs after the client gets the response.
- Set `[jobs] ENABLED=true` only when `rr` is running with a jobs pool; otherwise you get a warning + sync fallback.

**Config** — PHP `[jobs]` in `settings.ini`; queue driver in `.rr.yaml` (`memory` dev, `redis` prod):

```yaml
pipelines:
  moonlight:
    driver: redis
    config:
      addrs: ["127.0.0.1:6379"]
```

**Promise semantics (v3.0):** fulfillment means local execution or job **accepted** by RR (`JobSubmission`), not remote job completion.

Key classes: `Async`, `DeferredWorkBuffer`, `Background`, `JobSubmission`, `PromiseAwait`.

## Extension points

### Service providers (`Provider`)

Extend Pionia from **Composer packages** or your app via `Pionia\Base\Provider\Provider` (implements `ProviderContract`). `AppProvider` is a deprecated alias for backward compatibility.

| Hook | When it runs | Use for |
|------|----------------|---------|
| `middlewares(MiddlewareChain)` | Boot, before HTTP | Global middleware (`add`, `addBefore`, `addAfter`) |
| `authentications(AuthenticationChain)` | Boot | Auth backends (`addAuthentication`) |
| `commands(): array` | Boot | CLI commands (`alias => Command::class`) |
| `routes(PioniaRouter)` | Boot (after app routes) | Package API switches (`router($app)->switch(...)`) |
| `configureLogging(LogManager)` | Boot | Custom log channels |
| `configureCaching(CacheManager)` | Boot | Custom cache stores |
| `configureExceptions(ExceptionPipeline)` | Boot | Handlers, maps, `dontReport` |
| `onBooted()` | After all providers registered stacks | Container bindings, events |
| `onTerminate()` | CLI shutdown | Cleanup |

**Boot order:** resolve providers → middleware → auth → commands → configure* + `onBooted()` → provider routes.

**Registration** (pick one):

```ini
; environment/settings.ini
[app_providers]
billing=Vendor\Billing\BillingProvider
```

```php
// bootstrap/application.php (recommended — chainable)
pionia()->addAppProvider(\Application\Providers\AppProvider::class);
```

**Performance:** Pionia caches which providers finished boot (`bootstrapped_providers`). New providers in config boot incrementally; removed providers require `php pionia cache:clear`. Set `$app->appItemsCacheTTL` in bootstrap to control cache lifetime (default `0` = indefinite).

**Scaffold:** `php pionia make:provider AppProvider` → `providers/` + `[app_providers]` entry.

| Hook | Where |
|------|--------|
| Routes / switches | `bootstrap/routes.php` (app) or `Provider::routes()` (packages) |
| Middleware / auth | `settings.ini` sections or provider chains |
| Exception handler | `$app->exceptions()` or `Provider::configureExceptions()` |

## Testing policy

- Run `bin/test` (PHPUnit on PHP 8.5+). Output uses **TestDox** (readable test names) grouped by suite.
- `bin/test --verbose` (or `composer test:verbose`) prints each test name **as it runs**.
- Pass any PHPUnit args through: `bin/test --testsuite Console`, `bin/test tests/Http/FooTest.php`.
- **Every feature change must include tests** in the same PR.
- Test bootstrap: `tests/bootstrap.php` loads the example app with `PIONIA_TESTING` (null logger, `DEBUG=false`). Use `InteractsWithTestEnvironment::setDebugEnv()` when a test needs debug behavior.

### Composer scripts

| Script | Purpose |
|--------|---------|
| `composer test` | Full suite (TestDox, labeled by suite) |
| `composer test:verbose` | Full suite, live test name per test |
| `composer test:unit` | Unit tests only |
| `composer test:feature` | HTTP integration (`tests/Feature/`) |
| `composer test:console` | CLI smoke tests |
| `composer test:coverage` | Text + Clover report (`build/coverage.xml`) |
| `composer test:ci` | CI entry (coverage Clover; plain PHPUnit for CI) |

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

## Symfony dependency reduction (Phase 6)

Pionia is reducing Symfony surface area. **Removed** from `composer.json`:

| Package | Replaced by |
|---------|-------------|
| `symfony/http-kernel` | `RouteDispatcher` (native controller dispatch) |
| `symfony/mime` | `Pionia\Http\Mime\MimeType` |
| `symfony/asset` | `asset()` helper (no PathPackage) |
| `symfony/routing` | `RouteDefinition`, `RouteTable`, `RouteMatcher` |
| `symfony/http-foundation` | `Request`, `Response`, `BinaryFileResponse`, `ParameterBag`, `HeaderBag`, `FileBag`, `UploadedFile` |
| `symfony/filesystem` | `Pionia\Utils\Filesystem` |
| `symfony/dotenv` | `Pionia\Utils\Dotenv` |
| `symfony/uid` | `Pionia\Utils\Ulid` |
| `symfony/finder` | *(unused in framework; removed from require — dev tools may still pull transitively)* |
| `symfony/event-dispatcher` | `Pionia\Events\PioniaEventDispatcher` (PSR-14) |
| `symfony/cache` | `Pionia\Cache\CacheManager` + `CacheAdapterInterface` (PSR-16) |
| `symfony/process` | `Pionia\Process\Process` + `PhpExecutable` |
| `symfony/console` | `Pionia\Console\Application` + `Command` + `shell` REPL |

**Symfony in dev only:** `spiral/roadrunner-cli` may pull `symfony/console` transitively — not used by Pionia runtime code.

Routing exceptions: `Pionia\Http\Routing\Exception\RouteNotFoundException`, `MethodNotAllowedException`. HTTP 404 for missing resources: `Pionia\Exceptions\ResourceNotFoundException`.

## Packagist releases

Production archives **exclude** dev-only paths via `composer.json` `archive.exclude` and `.gitattributes` `export-ignore` (for GitHub source tarballs):

| Excluded | Why |
|----------|-----|
| `example/` | Dev sample app + generated API docs |
| `tests/` | PHPUnit suite |
| `docs/` | Contributor markdown (public guides live on [pionia.netlify.app](https://pionia.netlify.app)) |
| `bin/` | Dev scripts (`test`, `coverage-check`, `document-framework`) |
| `.github/` | CI workflows |
| `phpunit.xml`, `AGENTS.md`, etc. | Dev / contributor tooling |

Shipped artifact: **`src/Pionia/`** + `composer.json` + `README.md`.

### Release workflow

```bash
# Verify archive contents (also run in CI / Documentation suite)
composer clean:dev          # wipe generated docs + dev caches first
composer release:verify

# Full release: tests → verify zip → tag → push → GitHub release asset
bin/release v3.0.0

# Preview without git changes
bin/release v3.0.0 --dry-run
```

Pushing a `v*` tag also triggers `.github/workflows/release.yml` (tests, verify, attach zip to GitHub Release).

Consumer apps use `Application\` in their own repos; the monorepo maps that namespace under `autoload-dev` only.
