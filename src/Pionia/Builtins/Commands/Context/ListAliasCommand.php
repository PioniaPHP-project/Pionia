<?php

namespace Pionia\Pionia\Builtins\Commands\Context;

use Pionia\Pionia\Console\BaseCommand;
use Pionia\Pionia\Utils\Arrayable;

class ListAliasCommand extends BaseCommand
{

    protected array $aliases = ['alias', 'aliases'];

    protected string $name = 'alias:list';

    protected string $description = 'List all the aliases available in the application';

    protected string $help = 'This command lists all the aliases available in the application';

    public function handle(): void
    {
        $aliases = $this->getApplicationAliases();
        $this->info('AVAILABLE ALIASES IN THE APPLICATION CONTEXT');
        $this->table(['Name', 'Value', 'Is Directory?'], $aliases , 'box');
    }

    private function getApplicationAliases(): array | Arrayable
    {
        $aliases = $this->getApp()->getSilently('aliases')?->all();
        $mapped = [];
        foreach ($aliases as $key => $value) {

            $mapped[] = [$key, $value, yesNo(directoryFor($key) !== null)];
        }
        return $mapped;
    }
}
