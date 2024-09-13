<?php

namespace Pionia\Builtins\Commands\Generators;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Pionia\Collections\Arrayable;
use Pionia\Console\BaseCommand;
use Pionia\Http\Switches\BaseApiServiceSwitch;
use Pionia\Utils\Support;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Creates a new Pionia Switch in  Switches directory by running `pionia gen:switch {version}`
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class GenerateSwitch extends BaseCommand
{
    protected  string $help = 'Generates an authentication backend for pionia app.';
    protected string $name = 'make:switch';
    protected  string $title = 'Adds a new switch to pionia app';
    protected  string $description = 'Generates a switch for a pionia app. Switches map requests to services.';
    protected array $aliases = ['g:sw', 'switch', 'sw:on', 'switch:on'];

    private array $action = ['registerServices'];

    public function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the switch to generate'],
        ];
    }


    protected function handle(): int
    {
        $service_name = $this->argument("name");


        $this->info("Generating $service_name...");

        $this->generate($service_name);

        return Command::SUCCESS;
    }

    public function generate(string $className): void
    {
        $name = $className;
        if (!str_contains($className,'Switch')) {
            $name = Support::classify($className . 'Switch');
        }

        $file = new PhpFile;

        $ns = alias(\NAMESPACES::SWITCH_NS->name);

        $namespace = $file->addNamespace($ns);

        $file->addComment('This switch is auto-generated from pionia cli.');

        $namespace->addUse('Pionia\Http\Switches\BaseApiServiceSwitch');
        $namespace->addUse('Pionia\Collections\Arrayable');

        $klass = $namespace->addClass($name);

        $klass->setExtends(BaseApiServiceSwitch::class);

        $this->addActions($klass);

        $directory = alias(\DIRECTORIES::SWITCHES_DIR->name);

        $fs = new Filesystem();

        if (!$fs->exists($directory)) {
            $fs->mkdir($directory);
        }
        if ($fs->exists($directory . '/' . $name . '.php')) {
            $this->error("Switch $name already exists at $directory.");
            return;
        }
        $fs->dumpFile($directory.'/'.$name.'.php', $file);

        $this->info("Switch $name created at $directory.");
    }

    private function addActions(ClassType $class): void
    {
        foreach ($this->action as $action) {
            $class = $this->createActionMethod($class, $action);
        }
    }

    private function createActionMethod(ClassType $class, string $action): ClassType
    {
        $class->addMethod($action)
            ->setPublic()
            ->setReturnType(Arrayable::class)
            ->addComment("Register services here")
            ->addBody("return arr([")
            ->addBody("# Register your services here like `auth=>AuthService::class`")
            ->addBody("]);");
        return $class;
    }
}
