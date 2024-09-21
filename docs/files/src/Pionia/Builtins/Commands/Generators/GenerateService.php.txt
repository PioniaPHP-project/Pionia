<?php

namespace Pionia\Builtins\Commands\Generators;

use NAMESPACES;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Pionia\Collections\Arrayable;
use Pionia\Console\BaseCommand;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Services\Service;
use Pionia\Utils\Support;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\FileBag;

/**
 * For Creating a new service in the services directory by running `pionia gen:service {name}`
 *
 * @since 1.1.6 This command now supports creating generic services too!
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class GenerateService extends BaseCommand
{
    protected string $title = 'Generate A Pionia Service';
    protected string $description = 'Bootstraps a new service to in the services directory';
    protected string $name = 'make:service';
    protected array $aliases = ['g:s', 'gen:service', 'service'];
    protected string $help = 'Generates a new service in the services directory. Can be used to generate both basic and generic services.';

    protected array $defaults = ['get', 'create', 'list', 'delete', 'update'];

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the service to generate'],
        ];
    }

    protected function handle(): int
    {
        $service_name = $this->argument('name');

        $serviceType = $this->choice("Choose the type of service to scaffold", ['Generic', 'Basic'], 'Basic');

        if ($serviceType === 'Generic') {
            $extends = $this->choice("Choose one that best satisfies your needs from below", [
                'UniversalGenericService',//;
                'RetrieveListUpdateDeleteService',//;
                'RetrieveListUpdateService',//;
                'RetrieveListRandomService',//;
                'RetrieveListDeleteService',//;
                'RetrieveListCreateUpdateDeleteService',//;
                'RetrieveListCreateService',//;
                'RetrieveCreateUpdateService', //;
                'GenericService',
            ], 'UniversalGenericService');

            // we set actions to empty array since we are extending a generic service
            $actions = arr([$extends]);

            $this->info("You have chosen $extends as the base service");

            $targetTable = $this->ask("Please provide the target database table for this service", Support::toSnakeCase($service_name));
            if (empty($targetTable)) {
                $this->error("You must provide a target table");
                return Command::FAILURE;
            }
        } else {
            $extends = $this->ask("Provide comma(,) seperated actions you want to add by default, at least one is required", Support::arrayToString($this->defaults));
            if (empty($extends)) {
                $this->error("You must provide at least one action");
                return Command::FAILURE;
            }

            $actions = explode(',', $extends);
            $actions = array_map('trim', $actions);
            $actions = arr($actions);
        }

        $this->info("Generating $service_name service...");

        $this->generate($service_name, $actions, $serviceType, $targetTable??null);

        return Command::SUCCESS;
    }

    public function generate(string $serviceName, Arrayable $actions, string $serviceType, ?string $targetTable = null): void
    {
        $name = $serviceName;

        if (!str_contains($serviceName, 'Service')) {
            $name = Support::classify($serviceName.'Service');
        }

        $file = new PhpFile;

        $ns = alias(NAMESPACES::SERVICE_NS->name);
        $namespace = new PhpNamespace($ns);

        $file->addNamespace($namespace);

        $file->addComment('This service is auto-generated from pionia cli.');
        $file->addComment("Remember to register this service in any of your available switches.");

        if ($serviceType === 'Basic'){
            $namespace->addUse('Pionia\Http\Services\Service');
            $namespace->addUse('Pionia\Http\Response\BaseResponse');
            $namespace->addUse('Symfony\Component\HttpFoundation\FileBag');
            $namespace->addUse('Pionia\Collections\Arrayable');
        } else {
            // import the specific generic service the developer extended
            $gs = $actions->first() ?? 'UniversalGenericService';
            if ($gs === 'GenericService') {
                $namespace->addUse('Pionia\Http\Services\GenericService');
            } else {
                $namespace->addUse('Pionia\Http\Services\Generics\\' . $gs);
            }
        }


        $klass = $namespace->addClass($name);

        if ($serviceType === 'Basic') {
            $klass->setExtends(Service::class);
        } else {
            $ks = trim($actions->first() ?? '') ?? 'UniversalGenericService';
            if ($ks === 'GenericService') {
                $klass->setExtends('Pionia\Http\Services\GenericService');
            } else {
                $klass->setExtends('Pionia\Http\Services\Generics\\' . $gs);
            }
        }

        if ($serviceType === 'Generic') {
            $klass->addProperty('table')
                ->setPublic()
                ->setType('string')
                ->setValue($targetTable);
        } else {
            $this->addActions($klass, $actions, $serviceName);
        }

        $directory = alias(\DIRECTORIES::SERVICES_DIR->name);

        // create the directory if it doesn't exist and dump the file
        $fs = new Filesystem();
        if (!$fs->exists($directory)) {
            $fs->mkdir($directory);
        }
        if ($fs->exists($directory.'/'.$name.'.php')) {
            $this->error("Service $name already exists at $directory.");
            return;
        }

        $fs->dumpFile($directory.'/'.$name.'.php', $file);

        $this->info("Service $name created at $directory.");
    }

    private function addActions(ClassType $class, Arrayable $actions, $baseName): void
    {
        $this->info("Detecting actions");
        if ($actions->isEmpty()) {
            $this->info("Found none, defaulting to default actions - ".implode(", ", $this->defaults));
            $actions = arr($this->defaults);
        }

        $actions->each(function($action) use (&$class, $baseName) {
            $class = $this->createActionMethod($class, $action, $baseName);
        });

    }

    private function createActionMethod(ClassType $class, string $action, string $baseName): ClassType
    {
        if (empty($action)) {
            return $class;
        }
        if (empty($baseName)) {
            $baseName = $class->getName();
        }
        if (!str_contains($action, 'Action')) {
            $action = Support::toSnakeCase($action.'_'.$baseName.'Action');
        }

        $actionName = Support::toCamelCase($action);

        $method = $class->addMethod($actionName)
            ->setProtected()
            ->setReturnType(BaseResponse::class)
            ->addComment("$actionName action")
            ->addBody("return response(0, 'You have reached $action action');");

        $method->addParameter('data')
            ->setType(Arrayable::class);

        $method->addParameter('files')
            ->setType(FileBag::class)
            ->setDefaultValue(null)
            ->setNullable();

        $cname= $class->getName();
        $this->info("Added $action action as $actionName to $cname service.");
        return $class;
    }
}
