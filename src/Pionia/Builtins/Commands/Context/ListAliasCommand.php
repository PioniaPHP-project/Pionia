<?php

namespace Pionia\Builtins\Commands\Context;

use Pionia\Console\Command;
use Pionia\Realm\AppRealm;

class ListAliasCommand extends Command
{
    protected array $aliases = ['alias', 'aliases', 'list:aliases'];

    protected string $name = 'app:aliases';

    protected string $description = 'List all the aliases available in the application';

    protected string $help = 'This command lists all the aliases available in the application';

    protected function handle(): int
    {
        $aliases = $this->getApplicationAliases();
        $this->info('AVAILABLE ALIASES IN THE APPLICATION CONTEXT');
        $this->table(['Name', 'Value', 'Directory?'], $aliases, 'box');

        return Command::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function getApplicationAliases(): array
    {
        $aliases = realm()->getSilently(AppRealm::ALIASES_TAG);
        $items = is_object($aliases) && method_exists($aliases, 'all')
            ? $aliases->all()
            : (array) ($aliases ?? []);

        $mapped = [];
        foreach ($items as $key => $value) {
            $mapped[] = [(string) $key, (string) $value, yesNo(directoryFor((string) $key) !== null)];
        }

        return $mapped;
    }
}
