<?php

namespace Pionia\Codegens;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Pionia\Pionia\Http\Services\BaseRestService;
use Pionia\Response\BaseResponse;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
    private ?string $serviceType;
    private ?string $targetTable;

    public function __construct(
        string $name,
        ?array $actions = null,
        ?string $targetTable = null,
        ?string $serviceType = null,
        ?OutputInterface $output = null
    )
    {
        $this->name = $name;
        $this->actions = $actions;
        $this->output = $output;
        $this->serviceType = $serviceType;
        $this->targetTable = $targetTable;
        $this->backupName = ucfirst($name);
    }

    public function generate(?string $className = null, ?SymfonyStyle $io = null): void
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

        if ($this->serviceType === 'Basic'){
            $namespace->addUse('Pionia\Pionia\Http\Services\BaseRestService');
            $namespace->addUse('Pionia\Response\BaseResponse');
        } else {
            $gs = $this->actions[0]?? 'UniversalGenericService';
            $namespace->addUse('Pionia\Generics\\'.$gs);
        }

        $klass = $namespace->addClass($name);

        if ($this->serviceType === 'Basic') {
            $klass->setExtends(BaseRestService::class);
        } else {
            $ks = trim($this->actions[0]) ?? 'UniversalGenericService';
            $klass->setExtends('Pionia\Generics\\'.$ks);
        }

        if ($this->serviceType === 'Generic') {
            $klass->addProperty('table')
                ->setPublic()
                ->setType('string')
                ->setValue($this->targetTable);
        } else {
            $this->addActions($klass);
        }

        $directory = $this->dir.$name.'.php';

        $this->createFile($directory, $file);

        $io->success("Service $name created at $directory.");
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

//        $method->addParameter('files')
//            ->setType('?array');

        $this->log("Added $action action as $actionName to our $className service.");
        return $class;
    }


}
