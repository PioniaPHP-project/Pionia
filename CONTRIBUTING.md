# Contributing to PioniaCore

## Setup

```bash
git clone https://github.com/PioniaPHP-project/PioniaCore.git
cd PioniaCore
composer install
```

## Running tests

```bash
bin/test                  # preferred — avoids Composer PHAR noise on PHP 8.5
composer test             # same suite
composer test:feature     # HTTP integration only
composer test:coverage    # requires pcov or xdebug
```

Tests boot the **example app** from `tests/bootstrap.php`. See `AGENTS.md` for `Pionia\TestSuite` traits.

## Pull requests

1. **Include tests** for every behavior change (unit, feature, or console as appropriate).
2. Keep `bin/test` green on PHP 8.5.
3. CI enforces 50% line coverage on `src/Pionia/` — run `composer test:ci` locally if you have pcov installed.

## Example app changes

If you change services, switches, or `settings.ini`, update `example/README.md` and add or adjust tests under `tests/Feature/`.

## Documentation

- `AGENTS.md` — architecture and conventions for agents/contributors
- `example/README.md` — running the dev harness
- User-facing docs live in the separate [pionia-docs](https://pionia.netlify.app/) repo (sync via satellite plan)
