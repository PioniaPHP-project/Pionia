# Moonlight API documentation

Pionia’s public HTTP API is **not** REST resources — it is **Moonlight dispatch**: `{ "service", "action", ...params }` on versioned paths (`/api/v1/`).

**Response types:** Actions return `Pionia\Http\Response\ApiResponse` (the Moonlight JSON envelope). The switch layer converts it to `Pionia\Http\Response\Response` via `Response::fromEnvelope()` before sending HTTP.

This document defines how to annotate services so `pionia api:docs` and `pionia api:catalog` can generate OpenAPI + Markdown.

## Three documentation layers

| Layer | Audience | Tool | Output |
|-------|----------|------|--------|
| Framework internals | Core contributors | phpDocumentor (`composer document:framework`) | `build/docs/` |
| Moonlight API | App developers & consumers | `pionia api:docs` | `example/docs/api/openapi.json`, `index.md`, `index.html` |
| Architecture | Humans & agents | Hand-written | `AGENTS.md`, `CONTRIBUTING.md`, `example/README.md` |

## Service class header

```php
/**
 * Category management — CRUD and bulk operations.
 *
 * @moonlight-service category
 * @moonlight-version v1
 * @moonlight-auth partial
 * @moonlight-table categories
 */
class CategoryService extends Service
```

## Action method (PHPDoc)

```php
/**
 * List categories with optional pagination.
 *
 * @moonlight-action list
 * @moonlight-summary Returns paginated category rows
 * @moonlight-auth none
 * @moonlight-perm list_category
 * @moonlight-param int page Page number, default 1
 * @moonlight-param int limit Page size, default 20
 * @moonlight-return object items array of records, total int count
 * @moonlight-example {"service":"category","action":"list","page":1,"limit":20}
 */
protected function listAction(Arrayable $data): ApiResponse
```

## Action method (PHP attribute)

```php
use Pionia\Documentation\Attributes\MoonlightAction;

#[MoonlightAction(
    name: 'list',
    summary: 'Returns paginated category rows',
    auth: 'none',
    permissions: ['list_category'],
)]
protected function listAction(Arrayable $data): ApiResponse
```

PHPDoc tags and attributes merge; attributes override when both are present.

## Action validation (PHP attributes)

Validate request fields before the action body runs:

```php
use Pionia\Validations\Attributes\Validated;
use Pionia\Validations\Attributes\ValidateField;

#[Validated(rules: [
    'email' => 'required|email',
    'page' => 'integer|min:1',
])]
protected function listAction(Arrayable $data): ApiResponse
```

Or repeatable per-field attributes:

```php
#[ValidateField('email', 'required|email')]
protected function loginAction(Arrayable $data): ApiResponse
```

Same pipe syntax as `rules()`; custom rules from `validations()->extend()`. See user docs: Validations guide.

## Auth enforcement (PHP attributes)

Runtime auth (not just docs) — run before validation in `processAction`:

```php
use Pionia\Auth\Attributes\Authenticated;
use Pionia\Auth\Attributes\Can;
use Pionia\Auth\Attributes\CanAny;

#[Authenticated(except: ['login'])]
class MemberService extends Service
{
    #[Can('member.profile')]
    protected function profileAction(Arrayable $data): ApiResponse { … }

    #[Can(['member.update', 'admin'])]       // all required
    protected function updateAction(Arrayable $data): ApiResponse { … }

    #[CanAny(['member.edit', 'admin'])]      // any one
    protected function patchAction(Arrayable $data): ApiResponse { … }
}
```

When `@moonlight-auth` / `@moonlight-perm` are omitted, the catalog infers `required` and permissions from these attributes.

## Tag reference

| Tag / attribute | Required | Purpose |
|-----------------|----------|---------|
| `@moonlight-service` | On class | Switch alias (defaults to registry key) |
| `@moonlight-action` | On method | Request `action` string |
| `@moonlight-summary` | On method | One-line description |
| `@moonlight-auth` | On method | `none`, `optional`, or `required` |
| `@moonlight-perm` | On method | Permission slug (repeatable) |
| `@moonlight-param` | On method | `type name Description` |
| `@moonlight-return` | On method | Shape of `returnData` |
| `@moonlight-example` | On method | Example JSON payload |
| `@moonlight-table` | On class | Primary table for generic CRUD |
| `@moonlight-deprecated` | On method | Mark action deprecated |

## Inference (when tags are omitted)

- **Actions:** public/protected `*Action` methods and generic mixin macros
- **Action name:** snake_case of method name without `Action` suffix (e.g. `listAuthAction` → `list_auth`)
- **Auth:** merged with `$serviceRequiresAuth`, `$actionsRequiringAuth`, `$actionPermissions`

## Commands

```bash
composer document:api          # generate example/docs/api/ (includes index.html)
composer document:api:check    # CI drift check
php example/pionia api:catalog # JSON catalog to stdout
open http://127.0.0.1:8003/docs  # when DOCS_ENABLED or DEBUG
curl -s http://127.0.0.1:8003/api/v1/__catalog  # JSON catalog (same gate)
```

Runtime docs use `[docs] ENABLED` / `DOCS_ENABLED` and optional `TOKEN` / `DOCS_TOKEN` (see `example/environment/settings.ini`).

## OpenAPI profile

Moonlight OpenAPI exposes the real **`POST /api/{version}/`** dispatch operation. Every service action appears as a named request example and a `oneOf` request schema; `service` and `action` remain in the JSON body. OpenAPI permits only one operation for each method/path pair, so actions are not represented as synthetic paths.

## Response envelope

All actions return:

```json
{
  "returnCode": 0,
  "returnMessage": "...",
  "returnData": {},
  "extraData": null
}
```
