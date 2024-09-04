<?php

namespace Pionia\Codegens;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Pionia\Core\Interceptions\BaseMiddleware;
use Pionia\Request\Request;
use Pionia\Response\Response;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Code generator for creating middleware files in pionia
 */
class Middleware extends CodeGenerator
{
    private string $dir = BASEPATH.'/app/middlewares/';
    private string $namespace = 'application\middlewares';
    public string $sweetName = '';

    private array $actions = ['run'];

    public function __construct(string $name, ?OutputInterface $output = null)
    {
        $this->name = $name;
        $this->output = $output;

        $this->sweetName = $this->sweetName('Middleware');
    }

    public function generate(?string $className = null, ?SymfonyStyle $io = null): void
    {
        if ($className){
            $this->name = $className;
        }

        $this->name = $this->sweetName('Middleware');

        $file = new PhpFile;

        $namespace = new PhpNamespace($this->namespace);

        $file->addNamespace($namespace);

        $file->addComment('This middleware is auto-generated from pionia cli.');
        $file->addComment("Remember to register your middleware in index.php.");

        $namespace->addUse('Pionia\Core\Interceptions\BaseMiddleware');
        $namespace->addUse('Pionia\Request\Request');
        $namespace->addUse('Pionia\Response\Response');

        $klass = $namespace->addClass($this->name);

        $klass->setExtends(BaseMiddleware::class);

        $this->addActions($klass);

        $directory = $this->dir.$this->name.'.php';

        $this->createFile($directory, $file);
    }

    private function addActions(ClassType $class): void
    {
        foreach ($this->actions as $action) {
            $class = $this->createActionMethod($class, $action);
        }
    }

    private function createActionMethod(ClassType $class, string $action): ClassType
    {
        $method = $class->addMethod($action)
            ->setPublic()
            ->setReturnType('void')
            ->addComment("Implement the following to add logic on every request and response")
            ->addBody("if (\$response) {")
            ->addBody("")
            ->addBody("# your logic against response")
            ->addBody("")
            ->addBody("} else {")
            ->addBody("")
            ->addBody("# logic against request only")
            ->addBody("")
            ->addBody("}");

        $method->addParameter('request')
            ->setType(Request::class);

        $method->addParameter('response')
            ->setType(Response::class)
            ->setNullable();

        return $class;
    }


}
