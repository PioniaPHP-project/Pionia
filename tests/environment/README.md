# Test environment overrides

Optional fixtures for PHPUnit. The suite boots the **example app** via `tests/bootstrap.php` (`BASE_PATH` → `example/`).

Use `tests/environment/` only when a test needs isolated `settings.ini` fragments — most tests use `InteractsWithTestEnvironment` for `DEBUG` toggles.
