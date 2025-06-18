<?php class_exists('Pionia\Templating\TemplateEngine') or exit; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8
    <title><?php echo framework() ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo frameworkTag() ?>">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/styles.css">
    <title>
        <?php echo appName() ?>
    </title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
        }
        .hero {
            background: linear-gradient(120deg, #531863, #5f2a98);
            color: white;
            padding: 120px 0;
            text-align: center;
        }
        .pionia-color {
            background: linear-gradient(120deg, #531863, #5f2a98);
            color: #f0f0fb;
        }
        .code-block {
            background-color: #531863;
            border-radius: 10px;
            padding: 20px;
            color: #f0f0fb;
            font-family: monospace;
            white-space: pre-wrap;
        }
        .feature-icon {
            font-size: 2rem;
            color: #531863;
        }
        .nav-tabs .nav-link.active {
            background-color: #531863;
            color: #f0f0fb !important;
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark pionia-color">
    <div class="container">
        <div class="d-inline-flex align-content-center align-items-center">
            <img src="favicon.ico" style="height: 40px" alt="">
            <a class="navbar-brand fw-bold" href="#"><?php echo framework() ?></a>
        </div>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" target="_blank" href="https://github.com/PioniaPHP-project/Application"> <i class="bi bi-github"></i> GitHub</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div class="container bg-purple">
        <img src="/static/favicon.ico" alt="">
        <h1 class="display-3 fw-bold"><?php echo appName() ?></h1>
        <p class="mt-3 font-weight-bold" style="color: #21da21"><?php echo appName() ?> is up and running <?php if (isDebug()): ?> on port <?php echo env('SERVER_PORT') ?><?php echo apiBase() ?> <?php endif; ?> <i class="bi bi-open-browser"></i></p>
        <div class="mt-4">
            <a href="https://pionia.netlify.app/" target="_blank" class="btn btn-light btn-lg mt-4">Documentation</a>
            <a href="https://pionia.net/yourframework" class="btn btn-outline-light btn-lg mt-4">Star on GitHub</a>
        </div>
    </div>
</section>

<!-- Features -->
<section class="py-5 bg-light" id="features">
    <div class="container text-center">
        <h2 class="mb-4">Why <?php echo framework() ?>?</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <i class="bi bi-lightning-charge-fill feature-icon"></i>
                <h5 class="mt-3">Lightning Fast</h5>
                <p>Optimized for building RESTFUL APIs with minimal overhead.</p>
            </div>
            <div class="col-md-4">
                <i class="bi bi-gear-wide-connected feature-icon"></i>
                <h5 class="mt-3">Modular & Extensible</h5>
                <p>Use only the modules you need — middleware, auth, commands and more.</p>
            </div>
            <div class="col-md-4">
                <i class="bi bi-terminal feature-icon"></i>
                <h5 class="mt-3">Environment Config</h5>
                <p>Built-in `.env` support for clean and secure environment variables.</p>
            </div>
        </div>
    </div>
</section>

<!-- Application context -->
<?php if (isDebug()): ?>
<section class="py-5" id="examples">
    <div class="container">
        <h2 class="text-center mb-4">Application Context</h2>
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3 justify-content-center" id="codeTab" role="tablist">

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="command-tab" data-bs-toggle="tab" data-bs-target="#command" type="button">Commands</button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="routes-tab" data-bs-toggle="tab" data-bs-target="#routes" type="button">Routes</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="middlewares-tab" data-bs-toggle="tab" data-bs-target="#middlewares" type="button">Middlewares</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="authentications-tab" data-bs-toggle="tab" data-bs-target="#authentications" type="button">Authentications</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="env-tab" data-bs-toggle="tab" data-bs-target="#env" type="button">Environment</button>
            </li>
        </ul>

        <!-- Environment variables -->
        <div class="tab-content" id="codeTabContent">
            <div class="tab-pane fade show active" id="env" role="tabpanel">
                <table class="table table-bordered table-hover table-bordered table-responsive">
                    <thead>
                    <th>Key</th>
                    <th>Value</th>
                    </thead>
                    <tbody>
                    <?php foreach (envKeys() as $key): ?>
                        <tr>
                            <td><?php echo $key ?></td>
                            <td><?php indented($key, env($key)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <!-- commands -->
            <div class="tab-pane fade" id="command" role="tabpanel">
                <table class="table table-bordered table-hover table-bordered table-responsive">
                    <thead>
                        <th>Name</th>
                        <th>Class</th>
                    </thead>
                    <tbody>
                    <?php foreach (commands()->all() as $key => $value): ?>
                        <tr>
                            <td><?php echo $key ?></td>
                            <td><?php echo $value ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <!-- routes -->
            <div class="tab-pane fade" id="routes" role="tabpanel">
                <table class="table table-bordered table-hover table-bordered table-responsive">
                    <thead>
                    <th>Name</th>
                    <th>Path</th>
                    <th>Methods</th>
                    <th>Handler</th>
                    </thead>
                    <tbody>
                    <?php foreach (allRoutes()->all() as $key => $value): ?>
                        <tr>
                            <td><?php echo $key ?></td>
                            <td><?= $value->getPath() ?></td>
                            <td><?= arrayToString($value->getMethods()) ?></td>
                            <td><?= $value->getDefaults()['_controller'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>


            <!-- middlewares -->
            <div class="tab-pane fade" id="middlewares" role="tabpanel">
                <table class="table table-bordered table-hover table-bordered table-responsive">
                    <thead>
                    <th>Name</th>
                    <th>Value</th>
                    </thead>
                    <tbody>
                    <?php foreach (middlewares()->all() as $key => $value): ?>
                        <tr>
                            <td><?php echo $key ?></td>
                            <td><?php echo $value ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- authentications -->
            <div class="tab-pane fade" id="authentications" role="tabpanel">
                <table class="table table-bordered table-hover table-bordered table-responsive">
                    <thead>
                    <th>Name</th>
                    <th>Value</th>
                    </thead>
                    <tbody>
                    <?php foreach (authentications()->all() as $key => $value): ?>
                        <tr>
                            <td><?php echo $key ?></td>
                            <td><?php echo $value ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
<!-- Footer -->
<footer class="text-center py-4 bg-dark text-white">
    <img src="/static/pionia_logo.webp" class="mx-auto h-25 w-25" alt="<?php echo app()->getAppName() ?> - <?php echo env('APP_ENV') ?>"/>

    <div class="container">
        &copy; <?= date('Y') ?> <?php echo framework() ?> &mdash; ❤️ <?php echo frameworkTag() ?>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
