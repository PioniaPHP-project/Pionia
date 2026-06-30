# Pionia Core

PHP **Moonlight** REST framework — `{ service, action }` dispatch on versioned API paths. This repo is **`pionia/pionia-core`** (library). For a new application, use **`pionia/pionia-app`** or `pionia new`.

## Links

| Resource | URL |
|----------|-----|
| Developer guides | [pionia.netlify.app](https://pionia.netlify.app) |
| App template | [github.com/PioniaPHP-project/Pionia-App](https://github.com/PioniaPHP-project/Pionia-App) |
| Contributor / agent reference | [AGENTS.md](/AGENTS.md) |
| Moonlight doc standard | [docs/MOONLIGHT-DOCS.md](/docs/MOONLIGHT-DOCS.md) |

## Install (library)

```bash
composer require pionia/pionia-core
```

Requires **PHP 8.5+**.

## New application

```bash
# From this repo after composer install:
cd example && php pionia new ../my-app --install

# Or from Packagist (after v3 publish):
composer create-project pionia/pionia-app my-app
```

## Example app

Dev-only sample under `example/` (not shipped in Packagist releases):

```bash
composer install
cd example
php pionia serve
```

- Home: `http://127.0.0.1:8003/`
- Ping: `http://127.0.0.1:8003/api/v1/ping`
- Moonlight POST: `/api/v1/` with `{ "service", "action", ...params }`

RoadRunner: `php pionia runserver` (see `example/.rr.yaml`).

## Tests

```bash
bin/test                  # TestDox, labeled suites
composer test:verbose     # live test names
composer test:coverage    # requires pcov/xdebug
```

264+ tests on PHP 8.5. See [CONTRIBUTING.md](/CONTRIBUTING.md).

## Docs generation

```bash
composer document:api     # Moonlight OpenAPI from example app
composer document:framework
```

## Contributing

[CONTRIBUTING.md](/CONTRIBUTING.md) — tests required for feature changes.

## License

[MIT](https://github.com/PioniaPHP-project/.github/blob/main/profile/LICENCE.md)
