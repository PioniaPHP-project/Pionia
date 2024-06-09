<?php

namespace Pionia\codegens;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Pionia\core\helpers\ContextUserObject;
use Pionia\core\interceptions\BaseAuthenticationBackend;
use Pionia\core\interceptions\BaseMiddleware;
use Pionia\request\Request;
use Pionia\response\Response;
use Symfony\Component\Console\Output\OutputInterface;

class Middleware extends CodeGenerator
{
    private string $dir = BASEPATH.'/app/middlewares/';
    private string $namespace = 'application\middlewares';

    private array $actions = ['run'];

    public function __construct(string $name, ?OutputInterface $output = null)
    {
        $this->name = $name;
        $this->output = $output;
    }

    public function generate(?string $className = null): void
    {
        if ($className){
            $this->name = $className;
        }
        
        $name = $this->sweetName('Middleware');

        $file = new PhpFile;

        $namespace = new PhpNamespace($this->namespace);

        $file->addNamespace($namespace);

        $file->addComment('This middleware is auto-generated from pionia cli.');
        $file->addComment("Remember to register your middleware in index.php.");

        $namespace->addUse('Pionia\core\interceptions\BaseMiddleware');
        $namespace->addUse('Pionia\request\Request');
        $namespace->addUse('Pionia\response\Response');

        $klass = $namespace->addClass($name);

        $klass->setExtends(BaseMiddleware::class);

        $this->addActions($klass);

        $directory = $this->dir.$name.'.php';

        $this->createFile($directory, $file);

        $this->log("Middleware $name created at $directory.");
    }

    private function addActions(ClassType $class): ClassType
    {
        foreach ($this->actions as $action) {
            $class = $this->createActionMethod($class, $action);
        }
        return $class;
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