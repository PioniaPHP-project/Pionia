<?php

namespace Pionia\Builtins\Commands\Generators;

use DIRECTORIES;
use NAMESPACES;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Pionia\Base\Provider\Provider;
use Pionia\Console\Command;
use Pionia\Console\Input\InputArgument;
use Pionia\Utils\Filesystem;
use Pionia\Utils\Support;

/**
 * Scaffold an application or package service provider.
 */
class GenerateProvider extends Command
{
    protected string $title = 'Generate a Pionia provider';

    protected string $description = 'Creates a service provider class that can register middleware, auth, routes, and commands.';

    protected string $help = 'Providers extend Pionia\\Base\\Provider\\Provider and hook into the application boot lifecycle. Register the class under [app_providers] in settings.ini or via addAppProvider() in bootstrap/application.php.';

    protected string $name = 'make:provider';

    protected array $aliases = ['g:p', 'gen:provider', 'provider'];

    public function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'Provider class name (AppProvider, BillingProvider, …)'],
        ];
    }

    protected function handle(): int
    {
        $name = $this->argument('name');
        $this->info("Generating {$name} provider…");
        $this->generate($name);

        return Command::SUCCESS;
    }

    public function generate(string $className): void
    {
        $name = str_contains($className, 'Provider')
            ? Support::classify($className)
            : Support::classify($className.'Provider');

        $file = new PhpFile();
        $file->addComment('Auto-generated Pionia service provider.');

        $namespace = $file->addNamespace(namespaceFor(NAMESPACES::PROVIDER_NS->name));
        $namespace->addUse(Provider::class);

        $klass = $namespace->addClass($name);
        $klass->setExtends(Provider::class);
        $klass->addComment('Register middleware, auth, routes, or commands here.');
        $klass->addComment('Wire this class in bootstrap/application.php via addAppProvider() or in [app_providers].');

        $this->addStubMethods($klass);

        $directory = directoryPath(DIRECTORIES::PROVIDERS_DIR->name);
        $fs = new Filesystem();

        if (!$fs->exists($directory)) {
            $fs->mkdir($directory);
        }

        $path = $directory.'/'.$name.'.php';
        if ($fs->exists($path)) {
            $this->error("Provider {$name} already exists at {$directory}.");

            return;
        }

        $fs->dumpFile($path, $file);

        $fqcn = namespaceFor(NAMESPACES::PROVIDER_NS->name).'\\'.$name;
        addIniSection('app_providers', [Support::toSnakeCase($name) => $fqcn]);

        $this->info("Provider {$name} created at {$path}.");
        $this->info("Registered in environment settings as {$fqcn}.");
    }

    private function addStubMethods(ClassType $klass): void
    {
        $klass->addMethod('onBooted')
            ->setPublic()
            ->setReturnType('void')
            ->addBody('// Container bindings, event listeners, etc.');
    }
}
