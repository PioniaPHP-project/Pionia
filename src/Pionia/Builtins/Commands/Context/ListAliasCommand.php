<?php

namespace Pionia\Builtins\Commands\Context;

use Pionia\Collections\Arrayable;
use Pionia\Console\Command;

class ListAliasCommand extends Command
{

    protected array $aliases = ['alias', 'aliases', 'list:aliases'];

    protected string $name = 'app:aliases';

    protected string $description = 'List all the aliases available in the application';

    protected string $help = 'This command lists all the aliases available in the application';

    public function handle(): void
    {
        $aliases = $this->getApplicationAliases();
        $this->info('AVAILABLE ALIASES IN THE APPLICATION CONTEXT');
        $this->table(['Name', 'Value', 'Directory?'], $aliases , 'box');
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
