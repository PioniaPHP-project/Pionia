<?php

namespace Pionia\Builtins\Commands;

use Pionia\Console\BaseCommand;
use Pionia\Documentation\MoonlightCatalogExporter;
use Pionia\Documentation\MoonlightDocCollector;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;

class GenerateApiCatalog extends BaseCommand
{
    protected string $name = 'api:catalog';

    protected array $aliases = ['docs:catalog'];

    protected string $description = 'Print the Moonlight service/action catalog as JSON (same source as api:docs)';

    protected string $help = 'Outputs the live service/action catalog as JSON. Same collector as api:docs.';

    protected function getOptions(): array
    {
        return [
            ['output', 'o', InputOption::VALUE_OPTIONAL, 'Write JSON to file instead of stdout'],
        ];
    }

    protected function handle(): int
    {
        $catalog = (new MoonlightDocCollector())->collect();
        $json = (new MoonlightCatalogExporter())->toJson($catalog);

        $path = $this->option('output');
        if (is_string($path) && $path !== '') {
            file_put_contents($path, $json);
            $this->info("Wrote {$path}");

            return Command::SUCCESS;
        }

        $this->output->write($json);

        return Command::SUCCESS;
    }
}
