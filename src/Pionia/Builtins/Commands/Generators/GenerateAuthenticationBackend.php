<?php

namespace Pionia\Builtins\Commands\Generators;

use NAMESPACES;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Pionia\Auth\Authentication;
use Pionia\Auth\ContextUserObject;
use Pionia\Console\Command;
use Pionia\Http\Request\Request;
use Pionia\Utils\Support;
use Pionia\Console\Input\InputArgument;
use Pionia\Utils\Filesystem;

/**
 * For Creating a new authentication backend by running `php pionia make:auth {name}`
 *
 * @since 1.1.6 This command now writes the generated file to the `authentications` directory in your app
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class GenerateAuthenticationBackend extends Command
{
    protected string $title = 'Adds a new authentication backend';
    protected  string $help = 'Generates an authentication backend for pionia app.';
    protected string $description = 'Generates an authentication backend for pionia app.';
    protected string $name = 'make:auth';
    protected array $aliases = ['g:a', 'gen:auth'];

    protected array $action = ['authenticate'];

    public function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the authentication backend to generate'],
        ];
    }


    protected function handle(): int
    {
        $service_name = $this->argument("name");

        $this->info("Generating $service_name authentication in the authentications directory...");

        $this->generate($service_name);

        return Command::SUCCESS;
    }

    public function generate(string $className): void
    {
        $name = $className;
        if (!str_contains($className, 'Authentication')) {
            $name = Support::classify($className . 'Authentication');
        }

        $file = new PhpFile;

        $ns = namespaceFor(NAMESPACES::AUTHENTICATION_NS->name);

        $namespace = $file->addNamespace($ns);

        $file->addComment('This authentication backend is auto-generated from pionia cli.');

        $file->addComment("Remember to register your backend in index.php.");

        $namespace->addUse('Pionia\Auth\ContextUserObject');

        $namespace->addUse('Pionia\Auth\Authentication');

        $namespace->addUse('Pionia\Http\Request\Request');

        $klass = $namespace->addClass($name);

        $klass->setExtends(Authentication::class);

        $this->addActions($klass);

        $directory = directoryPath(\DIRECTORIES::AUTHENTICATION_DIR->name);

        $fs = new Filesystem();

        if (!$fs->exists($directory)) {
            $fs->mkdir($directory);
        }
        if ($fs->exists($directory . '/' . $name . '.php')) {
            $this->error("Authentication $name already exists at $directory.");
            return;
        }
        $fs->dumpFile($directory.'/'.$name.'.php', $file);

        // update the generated.ini file
        addIniSection('app_authentications', [Support::toSnakeCase(str_replace('Authentication', '', $name)) => $ns.'\\'.$name]);

        $this->info("Authentication $name created at $directory.");
    }

    private function addActions(ClassType $class): void
    {
        foreach ($this->action as $action) {
            $class = $this->createActionMethod($class, $action);
        }
    }

    private function createActionMethod(ClassType $class, string $action): ClassType
    {
        $method = $class->addMethod($action)
            ->setPublic()
            ->setReturnType(ContextUserObject::class)
            ->setReturnNullable()
            ->addComment("Implement this method and return your 'ContextUserObject'. You can query your database here too!")
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
