# Pionia helpers reference

Global functions in `src/Pionia/Utils/helpers.php`. Available after the realm boots (`bootstrap/routes.php` → HTTP or console). **Not** available in `bootstrap/application.php` before `AppRealm::create()`.

User-facing prose also lives in [pionia-docs — Helpers](https://pionia.netlify.app/documentation/helpers/).

---

## Application & container

| Helper | Returns | Purpose |
|--------|---------|---------|
| `app()` | `AppRealm` | Booted application singleton |
| `realm()` | `AppRealm` | Alias of `app()` |
| `container()` | `AppRealm` | Alias of `app()` |
| `pionia()` | `AppRealm` | Alias of `app()` |
| `framework()` | `string` | Framework display name |
| `version()` | `string` | Application version string |
| `appName()` | `string` | Application name from config |
| `isDebug()` | `bool` | Whether `DEBUG` / debug mode is on |

---

## Environment

| Helper | Purpose |
|--------|---------|
| `env(?string $key, mixed $default = null)` | Read env / settings value. Both args `null` → full env array |
| `setEnv(string $key, mixed $value)` | Set a request-scoped env override |
| `envKeys()` | List `.env` variable names loaded at boot |

---

## HTTP API paths

Use these in templates, frontend config, and tests — not hard-coded `/api/v1` strings.

| Helper | Example | Notes |
|--------|---------|-------|
| `apiBase()` | `/api/` | **Preferred** — value registered on the realm |
| `defaultApiVersion()` | `v1` | First registered switch version |
| `apiVersionPath()` | `/api/v1/` | Versioned prefix |
| `apiPingPath()` | `/api/v1/ping` | Health endpoint |
| `apiCatalogPath()` | `/api/v1/__catalog` | JSON action catalog (debug gate) |
| `baseUrl()` | `/api/` | Reads raw `API_BASE` env; prefer `apiBase()` |
| `serverPort(?int $override)` | `8003` | Resolved listen port |

---

## Database (Porm)

| Helper | Purpose |
|--------|---------|
| `table(string $name, ?string $alias = null, ?string $connection = null)` | Fluent query entry |
| `db(...)` | Alias of `table()` |
| `connectionManager()` | PDO pool (`ConnectionManager`) |

See `docs/PORM.md` and [pionia-docs — Database](https://pionia.netlify.app/documentation/database/).

---

## Cache & logging

| Helper | Purpose |
|--------|---------|
| `cache(?string $store = null)` | Default or named PSR-16 store |
| `logger(?string $channel = null)` | PSR-3 logger (default or named channel) |
| `report(Throwable\|string $message, array $context = [])` | Log via exception pipeline |
| `shouldLogResponses()` | Whether API responses are logged |

---

## Moonlight dispatch

| Helper | Purpose |
|--------|---------|
| `moonlight()` | Programmatic `{ service, action }` dispatch |
| `moonlight()->dispatch('auth', 'list_auth')` | Sync action call |
| `moonlight()->async('mail', 'send', $payload)` | Queue job; returns HTTP-shaped **202** when RR Jobs accepts |

Document actions with `@moonlight-*` tags — see `docs/MOONLIGHT-DOCS.md`.

---

## Background work: `defer` & `async`

PHP is **single-threaded**. Neither helper spawns a new OS thread. They schedule work to run **after the HTTP response body is sent** so the client is not blocked waiting for your closure.

### When to use what

| Goal | Use |
|------|-----|
| Log, webhook, quick cleanup after response | **`defer(function () { ... })`** |
| Same, but need `.then()` / `.catch()` / `await()` | **`async(function () { ... })`** or `(void) async(...)` |
| Email, reports, durable/heavy work | **`async('service', 'action', $payload)`** with RoadRunner Jobs |
| Return 202 + `job_id` to API caller | **`moonlight()->async(...)`** |

### `defer()` — recommended default

```php
protected function listAction(Arrayable $request): BaseResponse
{
    defer(function () use ($request) {
        logger()->info('Runs after client got the JSON response', ['id' => $request->get('id')]);
    });

    return response(0, 'OK', table('company')->all());
}
```

- Queues the closure; does **not** run it during the action.
- Runs when `WebApplication::fly()` finishes sending the response (`php pionia serve`, FPM) or after RoadRunner `respond()`.
- Requires `composer require react/promise` in your app.

### `async()` — two forms

**1. Closure** (same timing as `defer`, returns a promise):

```php
(void) async(function () {
    logger()->info('Post-response via promise API');
});
```

**2. Moonlight job** (service name string):

```php
async('mail', 'send_welcome', ['email' => $user->email]);
```

With `[jobs] ENABLED` and RoadRunner running, this queues on the jobs pool. On `php pionia serve` without jobs, it runs **synchronously after the response** (client already unblocked).

### What defer/async is **not**

- **Not multithreading** — long `sleep()` or heavy CPU in a deferred closure still blocks that PHP worker until it finishes.
- **Not a replacement for a queue** — use Moonlight jobs + RoadRunner for durable background processing.
- **Not for work inside the action** — code before `return response(...)` always runs before the client gets a reply.

### Runtime behaviour

| Runtime | Client released | Deferred closure runs |
|---------|-----------------|------------------------|
| `php pionia serve` | After `send()` + output flush + `Connection: close` | Same process, before script ends |
| PHP-FPM | `fastcgi_finish_request()` | Same worker, client disconnected |
| RoadRunner | After PSR-7 `respond()` | Same worker, before next request |

### `await()` and `promiseCatch()`

```php
$result = await(async(fn () => compute_something()));
promiseCatch($promise, fn (\Throwable $e) => logger()->error($e->getMessage()));
```

`await()` flushes the deferred buffer if work is still pending.

More detail: [pionia-docs — Background work](https://pionia.netlify.app/documentation/background-work/).

---

## Routing (bootstrap only)

| Helper | Purpose |
|--------|---------|
| `router(RealmContract $app)` | Build routes in `bootstrap/routes.php` |
| `route($app)` | **Deprecated** — use `router()` |
| `get(string $path)` / `post(string $path)` | Route definition helpers |
| `allRoutes()` | Registered route table |

---

## Templates & assets

| Helper | Purpose |
|--------|---------|
| `renderToString($file, ?array $data = [])` | Render template to string (preferred) |
| `render($file, ?array $data = [])` | **Deprecated** — renders and exits in FPM/CLI |
| `parseHtml($file, ?array $data = [])` | Parse template to structured data |
| `asset($file, ?string $dir = null)` | Resolve path under static dir |
| `alias($key)` | Path alias (`PUBLIC_DIR`, `STORAGE_DIR`, …) |

---

## Validation & responses

| Helper | Purpose |
|--------|---------|
| `validate(string $field, Arrayable\|Request\|Service $data)` | Field validator builder |
| `response(...)` | Moonlight JSON envelope |
| `cachedResponse(Service $instance, BaseResponse $response, mixed $ttl = 60)` | Cache action response |
| `recached(...)` | Shorthand for `cachedResponse(response(...), ...)` |
| `yesNo(mixed $cond, ?string $yes, ?string $no)` | Human-readable boolean label |
| `asBool(mixed $value)` | Coerce to bool |

---

## Utilities

| Helper | Purpose |
|--------|---------|
| `arr(?array $array)` | Dot-notation `Arrayable` wrapper |
| `tap($value, ?callable $callback)` | Run side effect, return original value |
| `now()` | Current `DateTime` via Carbon |
| `timeAgo($datetime)` | Human-readable relative time |
| `time_ago(int\|float $time)` | **Deprecated** — use `timeAgo()` |
| `slugify(string $text)` | URL-safe slug |
| `addIniSection(string $section, array $values)` | Append INI section at runtime |

---

## Exceptions

| Helper | Purpose |
|--------|---------|
| `pionia_handle_exception(Throwable $e, ?Request $request)` | Render via exception pipeline |
| `report($e)` | Log only, no HTTP response |

---

## Deprecated aliases (keep for BC)

| Deprecated | Use instead |
|------------|-------------|
| `route()` | `router()` |
| `render()` | `renderToString()` |
| `time_ago()` | `timeAgo()` |
