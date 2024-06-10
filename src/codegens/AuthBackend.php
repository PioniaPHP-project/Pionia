<?php

namespace Pionia\codegens;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Pionia\core\helpers\ContextUserObject;
use Pionia\core\interceptions\BaseAuthenticationBackend;
use Pionia\request\Request;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Code generator for creating Authentication Backends
 */
class AuthBackend extends CodeGenerator
{
    private string $dir = BASEPATH.'/app/authenticationBackends/';
    private string $namespace = 'application\authenticationBackends';

    private array $actions = ['authenticate'];

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
        
        $name = $this->sweetName('AuthBackend');

        $file = new PhpFile;

        $namespace = new PhpNamespace($this->namespace);

        $file->addNamespace($namespace);

        $file->addComment('This authentication backend is auto-generated from pionia cli.');
        $file->addComment("Remember to register your backend in index.php.");

        $namespace->addUse('Pionia\core\helpers\ContextUserObject');
        $namespace->addUse('Pionia\core\interceptions\BaseAuthenticationBackend');
        $namespace->addUse('Pionia\request\Request');

        $klass = $namespace->addClass($name);

        $klass->setExtends(BaseAuthenticationBackend::class);

        $this->addActions($klass);

        $directory = $this->dir.$name.'.php';

        $this->createFile($directory, $file);

        $this->log("Authentication Backend $name created at $directory.");
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
            ->setReturnType(ContextUserObject::class)
            ->addComment("Implement this method and return your 'ContextUserObject'. You can use Porm here too!")
            ->addBody("\$userObj = new ContextUserObject();")
            ->addBody("")
            ->addBody("# your logic here...")
            ->addBody("")
            ->addBody("return \$userObj;");

        $method->addParameter('request')
            ->setType(Request::class);

        return $class;
    }


}