<?php

namespace Pionia\Codegens;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Pionia\Request\BaseRestService;
use Pionia\Response\BaseResponse;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Code generator for creating new services in Pionia
 */
class Service extends CodeGenerator
{
    private string $dir = BASEPATH.'/app/services/';
    private string $namespace = 'application\services';
    private ?array $actions;

    private ?string $backupName = null;

    private array $defaults = [
        'get',
        'create',
        'list',
        'delete',
    ];

    public function __construct(string $name, ?array $actions = null, ?OutputInterface $output = null)
    {
        $this->name = $name;
        $this->actions = $actions;
        $this->output = $output;
        $this->backupName = ucfirst($name);
    }

    public function generate(?string $className = null): void
    {
        if ($className){
            $this->name = $className;
        }

        $name = $this->sweetName('Service');

        $file = new PhpFile;

        $namespace = new PhpNamespace($this->namespace);

        $file->addNamespace($namespace);

        $file->addComment('This service is auto-generated from pionia cli.');
        $file->addComment("Remember to register your this service as $name in your service switch.");

        $namespace->addUse('Pionia\Response\BaseResponse');
        $namespace->addUse('Pionia\Request\BaseRestService');

        $klass = $namespace->addClass($name);

        $klass->setExtends(BaseRestService::class);

        $this->addActions($klass);

        $directory = $this->dir.$name.'.php';

        $this->createFile($directory, $file);

        $this->log("Service $name created at $directory.");
    }

    private function addActions(ClassType $class): void
    {
        $this->log("Detecting actions");
        if (count($this->actions) < 1){
            $this->log("Found none, defaulting to default actions - ".implode(", ", $this->defaults));
            $this->actions = $this->defaults;
        }
        foreach ($this->actions as $action) {
            $class = $this->createActionMethod($class, $action);
        }
    }

    private function createActionMethod(ClassType $class, string $action): ClassType
    {
        $className = $class->getName();

        $actionName = $action.$this->backupName;

        $method = $class->addMethod($actionName)
            ->setProtected()
            ->setReturnType(BaseResponse::class)
            ->addComment("In the request object, you can hit this service using - {'ACTION': '$actionName', 'SERVICE':'$className' ...otherData}")
            ->addBody("return BaseResponse::JsonResponse(0, 'You have reached $action action');");

        $method->addParameter('data')
            ->setType('?array');

        $method->addParameter('files')
            ->setType('?array');

        $this->log("Added $action action as $actionName to our $className service.");
        return $class;
    }


}
