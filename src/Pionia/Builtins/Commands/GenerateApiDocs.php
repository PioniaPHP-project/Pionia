<?php

namespace Pionia\Builtins\Commands;

use Pionia\Console\BaseCommand;
use Pionia\Documentation\ApiDocsUiExporter;
use Pionia\Documentation\MarkdownExporter;
use Pionia\Documentation\MoonlightDocCollector;
use Pionia\Documentation\OpenApiExporter;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Utils\Filesystem;

class GenerateApiDocs extends BaseCommand
{
    protected string $name = 'api:docs';

    protected array $aliases = ['make:api-docs', 'docs:api'];

    protected string $description = 'Generate Moonlight API reference (OpenAPI + Markdown) from service action comments';

    protected string $help = 'Scans registered switches and services, parses @moonlight-* PHPDoc tags, and writes docs/api/.';

    protected function getOptions(): array
    {
        return [
            ['format', 'f', InputOption::VALUE_OPTIONAL, 'Output formats: openapi,markdown,html (comma-separated)', 'openapi,markdown'],
            ['output', 'o', InputOption::VALUE_OPTIONAL, 'Destination directory', null],
            ['ui', null, InputOption::VALUE_NONE, 'Also write index.html (Scalar / Swagger-like UI)'],
            ['check', null, InputOption::VALUE_NONE, 'Fail if generated output differs from committed files'],
        ];
    }

    protected function handle(): int
    {
        $formats = array_map('trim', explode(',', (string) $this->option('format')));
        $outputDir = $this->resolveOutputDir();
        $check = (bool) $this->option('check');

        $catalog = (new MoonlightDocCollector())->collect();
        $files = [];

        if (in_array('openapi', $formats, true)) {
            $files['openapi.json'] = (new OpenApiExporter())->export($catalog);
        }

        if (in_array('markdown', $formats, true)) {
            $files['index.md'] = (new MarkdownExporter())->export($catalog);
        }

        if (in_array('html', $formats, true) || $this->option('ui')) {
            $files['index.html'] = (new ApiDocsUiExporter())->render($catalog, 'openapi.json');
        }

        if ($files === []) {
            $this->error('No output formats selected.');

            return Command::FAILURE;
        }

        $fs = new Filesystem();
        if (!$check && !$fs->exists($outputDir)) {
            $fs->mkdir($outputDir);
        }

        foreach ($files as $name => $content) {
            $path = $outputDir . DIRECTORY_SEPARATOR . $name;

            if ($check) {
                if (!is_file($path)) {
                    $this->error("Missing committed file: {$path}. Run api:docs to generate.");

                    return Command::FAILURE;
                }

                $existing = file_get_contents($path);
                if ($existing !== $content) {
                    $this->error("Drift detected in {$name}. Run api:docs to regenerate.");

                    return Command::FAILURE;
                }

                continue;
            }

            file_put_contents($path, $content);
            $this->info("Wrote {$path}");
        }

        if ($check) {
            $this->info('API docs are up to date.');
        } else {
            $actionCount = 0;
            foreach ($catalog->versions as $services) {
                foreach ($services as $service) {
                    $actionCount += count($service->actions);
                }
            }
            $this->info("Documented {$actionCount} actions across " . count($catalog->versions) . ' version(s).');
        }

        return Command::SUCCESS;
    }

    private function resolveOutputDir(): string
    {
        $custom = $this->option('output');
        if (is_string($custom) && $custom !== '') {
            return rtrim($custom, DIRECTORY_SEPARATOR);
        }

        $base = defined('BASE_PATH') ? BASE_PATH : getcwd();

        return $base . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'api';
    }
}
