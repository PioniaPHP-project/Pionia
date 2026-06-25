# Pionia Core

##### This is the core of the Pionia Framework

[//]: # (Adding get api endpoints like /api/v1/service/action, here we also send get requests normally!)
## Installation

```bash
composer require pionia/pionia-core
```
This is meant for the core developers of the Pionia Core not the framework itself.
If you are looking to use or get started with the framework, please find the [Pionia Framework here](https://github.com/PioniaPHP-project/Application)

## Usage

#### [Go to the documentation here](https://pioniaphp-project.github.io/Pionia/)

# Road Map for the Pionia Framework(Subject to change)

> ##### Coverage 25/26 -- 96%

## Packages covered

- [x] Pionia\Core
- [x] Authentication
- [x] Database
- [x] File System
-  Mail
- [x] Session
- [x] Validation
- [x] Templating engine
- [x] Routing
- [x] Middleware
- [x] Request
- [x] Response
- [x] Services
- [x] Switches
- [x] Config and Environments
- [x] Logging
- [x] Cors
- [x] HTTP Kernel
- [x] Events
- [x] Console
- [x] Cache
- [x] Exception handling
- [x] Helper functions
- [x] CLI Bootstrapping
- [x] Uploading and Serving Media Files
- [x] Collections

## Setting up the this project

### Prerequisites

- PHP 8.5 or higher
- Composer
- Git
- Any Editor/IDE of your choice preferably PHPStorm or Intellij IDEA

### Installation

Clone the repository

```bash
git clone https://github.com/PioniaPHP-project/PioniaCore.git
```

Change directory to the project folder

```bash
cd PioniaCore
```

Install the dependencies

```bash
composer install
```

### Running the example app

From the `example/` directory:

```bash
cd example
php pionia serve
```

Then open `http://127.0.0.1:8003/` (port from `example/environment/.env`).

API routes are **versioned** — register switches in `bootstrap/routes.php` (e.g. `v1` → `/api/v1/`):

```bash
curl -s http://127.0.0.1:8003/api/v1/ping
curl -s -X POST http://127.0.0.1:8003/api/v1/ \
  -H "Content-Type: application/json" \
  -d '{"service":"auth","action":"list_auth"}'
```

Helpers: `apiVersionPath()`, `apiPingPath()`, `defaultApiVersion()` in `src/Pionia/Utils/helpers.php`.

### Running the tests

Prefer **`bin/test`** (or `./vendor/bin/phpunit`) for clean output on PHP 8.5:

```bash
bin/test
```

`composer test` works too, but PHP 8.5 may print deprecation notices from **Composer's own PHAR** (`react/promise` inside `/usr/local/bin/composer`) before PHPUnit runs. Those are not from PioniaCore and do not affect results.

To keep using the `composer` CLI with quieter output (suppresses deprecations from Composer's PHAR on PHP 8.5):

```bash
php -d error_reporting=24575 $(which composer) test
```

### Compiling the core dev docs

```bash
composer document
```

### Contributing

Please read the [CONTRIBUTING.md](/CONTRIBUTING.md) file for more information on how to contribute to this project.

### Authors

- [**Jet2018**](https://github.com/jet2018)

## License
[MIT License](https://github.com/PioniaPHP-project/.github/blob/main/profile/LICENCE.md)
