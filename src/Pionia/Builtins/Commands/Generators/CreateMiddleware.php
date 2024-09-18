<?php

namespace Pionia\Builtins\Commands\Generators;

use NAMESPACES;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Pionia\Console\BaseCommand;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\Middleware;
use Pionia\Utils\Support;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;

/**
 * For Creating a new middleware in the middlewares directory by running `pionia gen:middleware {name}`
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class CreateMiddleware extends BaseCommand
{
    protected string $title = 'Adds a new middleware to pionia app';
    protected  string $help = 'Generates a middleware for a pionia app. Middlewares run on every request and response. \n
    Middlewares are added to the middleware chain automatically. Upon creation, you can add your logic to the middleware class and register it in the middleware sections of the settings file.';
    protected string $description = 'Generates a middleware for a pionia app. Middlewares run on every request and response.';
    protected string $name = 'make:middleware';
    protected array $aliases = ['g:m', 'gen:mid', 'gen:mid', 'gen:middle', 'gen:middleware'];


    public function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the middleware to generate. Pionia automatically appends Middleware to the name'],
        ];
    }

    protected function handle(): int
    {
        $name = $this->argument("name");
        $this->info("Generating $name middleware in the middlewares directory...");
        $this->generate($name);
        return Command::SUCCESS;
    }

    public function generate(string $className): void
    {
        $name = $className;
        if (!str_contains($className,'Middleware')) {
            $name = Support::classify($className . 'Middleware');
        }

        $file = new PhpFile;

        $ns = alias(NAMESPACES::MIDDLEWARE_NS->name);

        $namespace = $file->addNamespace($ns);

        $file->addComment('This middleware is auto-generated from pionia cli.');

        $file->addComment("Remember to register your middleware in bootstrap/application.php or in any ini environment file under [middlewares] section.");

        $namespace->addUse('Pionia\Middlewares\Middleware');
        $namespace->addUse('Pionia\Http\Request\Request');
        $namespace->addUse('Pionia\Http\Response\Response');

        $klass = $namespace->addClass($name);

        $klass->setExtends(Middleware::class);

        $this->addActionMethods($klass);

        $directory = alias(\DIRECTORIES::MIDDLEWARE_DIR->name);

        $fs = new Filesystem();

        if (!$fs->exists($directory)) {
            $fs->mkdir($directory);
        }
        if ($fs->exists($directory . '/' . $name . '.php')) {
            $this->error("Middleware $name already exists at $directory.");
            return;
        }
        $fs->dumpFile($directory.'/'.$name.'.php', $file);

        // update the generated.ini file
        addIniSection('middlewares', [$name => $ns.'\\'.$name]);

        $this->info("Middleware $name created at $directory.");
    }

    private function addActionMethods(ClassType $class): void
    {
        $OnRequestMethod = $class->addMethod('onRequest')
            ->setPublic()
            ->addBody("# You implementation against incoming request here")
            ->addBody("");

        $OnRequestMethod->addParameter('request')
            ->setType(Request::class);

        $OnResponseMethod = $class->addMethod('onResponse')
            ->setPublic()
            ->addBody("# You implementation against every response here")
            ->addBody("");

        $OnResponseMethod->addParameter('response')
            ->setType(Response::class);
    }
}
