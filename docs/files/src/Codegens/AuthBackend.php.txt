<?php

namespace Pionia\Codegens;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Pionia\Core\Helpers\ContextUserObject;
use Pionia\Core\Interceptions\BaseAuthenticationBackend;
use Pionia\Request\Request;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates Authentication Backends
 */
class AuthBackend extends CodeGenerator
{
    private string $dir = BASEPATH.'/app/authentications/';
    private string $namespace = 'application\authentications';

    private array $actions = ['authenticate'];

    public function __construct(string $name, ?OutputInterface $output = null)
    {
        $this->name = $name;
        $this->output = $output;
    }

    public function generate(?string $className = null, ?SymfonyStyle $io= null): void
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

        $namespace->addUse('Pionia\Core\Helpers\ContextUserObject');
        $namespace->addUse('Pionia\Core\Interceptions\BaseAuthenticationBackend');
        $namespace->addUse('Pionia\Request\Request');

        $klass = $namespace->addClass($name);

        $klass->setExtends(BaseAuthenticationBackend::class);

        $this->addActions($klass);

        $directory = $this->dir.$name.'.php';

        $this->createFile($directory, $file);

        $io->success("Authentication Backend $name created at $directory.");
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
            ->setReturnType(ContextUserObject::class)
            ->setReturnNullable()
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
