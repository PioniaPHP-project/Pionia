<?php

namespace Pionia\Codegens;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Pionia\Core\BaseApiServiceSwitch;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Code generator for creating middleware files in pionia
 */
class GenerateSwitch extends CodeGenerator
{
    private string $dir = BASEPATH.'/app/switches/';
    private string $namespace = 'application\switches';
    public string $sweetName = '';

    private array $actions = ['registerServices'];

    public function __construct(string $name, ?OutputInterface $output = null)
    {
        $this->name = $name;
        $this->output = $output;

        $this->sweetName = $this->sweetName('Switch');
    }

    public function generate(?string $className = null, ?SymfonyStyle $io = null): void
    {
        if ($className){
            $this->name = $className;
        }

        $this->name = $this->sweetName('Switch');

        $file = new PhpFile;

        $namespace = new PhpNamespace($this->namespace);

        $file->addNamespace($namespace);

        $file->addComment('This switch is auto-generated from pionia cli.');
        $file->addComment("Remember to register it in your routes.php using our `addSwitchFor`.");

        $namespace->addUse('Pionia\Core\BaseApiServiceSwitch');

        $klass = $namespace->addClass($this->name);

        $klass->setExtends(BaseApiServiceSwitch::class);

        $this->addActions($klass);

        $directory = $this->dir.$this->name.'.php';

        if (is_file($directory)) {
            $io->error("Switch $this->name already exists");
        } else {
            $io->success("Switch $this->name created successfully");
            $this->createFile($directory, $file);
        }
    }

    private function addActions(ClassType $class): void
    {
        foreach ($this->actions as $action) {
            $class = $this->createActionMethod($class, $action);
        }
    }

    private function createActionMethod(ClassType $class, string $action): ClassType
    {
        $class->addMethod($action)
            ->setPublic()
            ->setReturnType('array')
            ->addBody("return [];");

        return $class;
    }


}
