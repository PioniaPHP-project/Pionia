<?php

namespace Pionia\Builtins\Commands\Generators;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Pionia\Console\BaseCommand;
use Pionia\Utils\Support;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This command is used to generate a new custom command.
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class GenerateCommand extends BaseCommand
{
    protected  string $help = 'Bootstraps a new command ready for registration.';
    protected string $name = 'make:command';
    protected  string $title = 'Custom Command Generator';
    protected  string $description = 'Generates a command for a pionia cli. You can place all your logic in the handle method.';
    protected array $aliases = ['g:c', 'command', 'gen:command'];

    private array $action = ['handle'];

    public function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the command to generate'],
        ];
    }

    public function getOptions(): array
    {
        return [
            ['group', 'g', InputArgument::OPTIONAL, 'The namespace of the command to generate', 'custom'],
        ];
    }


    protected function handle(): int
    {
        $name = $this->argument("name");

        $command_group = $this->option("group");

        if (empty($command_group) || $command_group === 'custom') {
            $command_group = $this->ask("Which namespace do you want this command in?", $command_group);
        }

        $proceed = $this->confirm("This will create a command in the format $command_group:$name, do you want to proceed", true);
        if (!$proceed) {
            $this->info("Command generation aborted.");
            return Command::SUCCESS;
        }
        title:
        $title = $this->ask("What is the title of this command?");
        if (empty($title)) {
            $this->warn("Title cannot be empty.");
            goto title;
        }

        description:
        $description = $this->ask("What is the description of this command?");
        if (empty($description)) {
            $this->warn("Description cannot be empty.");
            goto description;
        }

        help:
        $help = $this->ask("What is the help message for this command?");
        if (empty($help)) {
            $this->warn("Help message cannot be empty.");
            goto help;
        }

        $aliases = [];

        alias:
        $addMore = $this->confirm(count($aliases) > 0 ? "Do you want to add another alias?": "Do you want to add command aliases?", false);
        if ($addMore) {
            $alias = $this->ask("Please enter the command alias you want to add.");
            if (empty($alias)) {
                $this->warn("We can't add an empty alias.");
            } else {
                $aliases[] = trim($alias);
            }
            goto alias;
        }

        $arguments = [];
        arguments:
        $addMore = $this->confirm(count($arguments) > 0 ? "Do you want to add another argument?" :"Do you want to add command arguments?", false);
        if ($addMore) {
            $argument = $this->ask("Please enter the command argument you want to add.");
            if (empty($argument)) {
                $this->warn("We can't add an empty argument.");
            } else {
                $kind = $this->choice("Argument Kind", ['Optional', 'Required', 'Array'], 'Optional');
                argument_description:
                $description = $this->ask("Please enter the description of the argument.");
                if (empty($description)) {
                    $this->warn("We can't add an empty description.");
                    goto argument_description;
                }

                if ($kind === 'Optional') {
                    $defaultValue = $this->ask("Do you want to add a default value for this optional argument? (press enter to skip)", null);
                    if ($defaultValue) {
                        $arguments[] = [
                            'name' => trim($argument),
                            'mode' => $kind,
                            'description' => $description,
                            'default' => $defaultValue
                        ];
                    } else {
                        $arguments[] = [
                            'name' => trim($argument),
                            'mode' => $kind,
                            'description' => $description,
                        ];
                    }
                } else {
                    $arguments[] = [
                        'name' => trim($argument),
                        'mode' => $kind,
                        'description' => $description,
                    ];
                }
            }
            goto arguments;
        }

        $options = [];
        options:
        $addMore = $this->confirm(count($options) >0 ? "Do you want to add another command option?":"Do you want to add command options?", false);
        if ($addMore) {
            $option = $this->ask("Please enter the command option you want to add.");
            if (empty($option)) {
                $this->warn("We can't add an empty option.");
            } else {
                $shortCut = $this->ask("Please enter the short option for $option.", strtolower(substr($option, 0, 1)));
                $mode = $this->choice("Option Mode", ['None','Optional', 'Required', 'Array', 'Negate-able'], 'None');
                option_description:
                $description = $this->ask("Please enter the description of $option option.");
                if (empty($description)) {
                    $this->warn("We can't add an empty description.");
                    goto option_description;
                }
                if ($mode === 'Optional') {
                    $defaultValue = $this->ask("Do you want to add a default value for this optional option? (press enter to skip)", null);
                    if ($defaultValue) {
                        $options[] = [
                            'name' => trim($option),
                            'shortcut' => $shortCut,
                            'mode' => $mode,
                            'description' => $description,
                            'default' => $defaultValue
                        ];
                    } else {
                        $options[] = [
                            'name' => trim($option),
                            'shortcut' => $shortCut,
                            'mode' => $mode,
                            'description' => $description,
                        ];
                    }
                } else {
                    $options[] = [
                        'name' => trim($option),
                        'shortcut' => $shortCut,
                        'mode' => $mode,
                        'description' => $description,
                    ];
                }
            }
            goto options;
        }

        $clean_name = $command_group.':'.$name;
        $this->info("Generating $name command ($clean_name)...");
        $this->generate($name, $clean_name, $title, $description, $help, $aliases, $arguments, $options);
        return Command::SUCCESS;
    }

    public function generate(string $className, $clean_name, $title, $description, $help, $aliases, $arguments, $options): void
    {
        $name = $className;
        if (!str_contains($className,'Command')) {
            $name = Support::classify($className . 'Command');
        }

        $file = new PhpFile;

        $ns = alias(\NAMESPACES::COMMAND_NS->name);

        $namespace = $file->addNamespace($ns);

        $file->addComment('This command is auto-generated from pionia cli.');

        $namespace->addUse('Pionia\Console\BaseCommand');
        $namespace->addUse('Symfony\Component\Console\Input\InputArgument');
        $namespace->addUse('Symfony\Component\Console\Input\InputOption');
        $namespace->addUse('Symfony\Component\Console\Command\Command');

        $klass = $namespace->addClass($name);

        $klass->setExtends(BaseCommand::class);

        $klass->addProperty('aliases')
            ->setProtected()
            ->setType('array')
            ->setValue($aliases)
            ->addComment("The aliases for the command.");

        $klass->addProperty('help')
            ->setProtected()
            ->setType('string')
            ->setValue($help)
            ->addComment("The help message for the command.");

        $klass->addProperty('description')
            ->setProtected()
            ->setType('string')
            ->setValue($description)
            ->addComment("The description of the command.");

        $klass->addProperty('title')
            ->setProtected()
            ->setType('string')
            ->setValue($title)
            ->addComment("The title of the command.");

        $klass->addProperty('name')
            ->setProtected()
            ->setType('string')
            ->setValue($clean_name)
            ->addComment("The name of the command.");

        $this->addArgumentsAction($klass, $arguments);
        $this->addOptionsAction($klass, $options);
        $this->addHandler($klass, $options, $arguments);

        $directory = alias(\DIRECTORIES::COMMANDS_DIR->name);

        $fs = new Filesystem();

        if (!$fs->exists($directory)) {
            $fs->mkdir($directory);
        }
        if ($fs->exists($directory . '/' . $name . '.php')) {
            $this->error("Command $name already exists at $directory.");
            return;
        }
        $fs->dumpFile($directory.'/'.$name.'.php', $file);

        // update the generated.ini file
        addIniSection('commands', [$clean_name => $ns.'\\'.$name]);

        $this->info("Command $name created at $directory.");
    }

    private function addArgumentsAction(ClassType $klass, array $arguments): void
    {
        $argumentsHandler = $klass->addMethod('getArguments')
            ->setPublic()
            ->setReturnType('array')
            ->addComment("Get the console command arguments.");
        $argumentsHandler->addBody("return [");
        foreach ($arguments as $argument) {
            $type = match ($argument['mode']) {
                'Required' => '::REQUIRED',
                'Array' => '::IS_ARRAY',
                'Optional' => '::OPTIONAL',
            };
            if (isset($argument['default'])) {
                $argumentsHandler->addBody("    ['". $argument['name'] ."', ". 'InputArgument'.$type .", '". $argument['description'] ."', '". $argument['default'] ."']");
            } else {
                $argumentsHandler->addBody("    ['". $argument['name'] ."', ". 'InputArgument'.$type .", '". $argument['description'] ."']");
            }
        }
        $argumentsHandler->addBody("];");
    }

    private function addOptionsAction(ClassType $klass, array $options): void
    {
        $argumentsHandler = $klass->addMethod('getOptions')
            ->setPublic()
            ->setReturnType('array')
            ->addComment("Get the console command options.");
        $argumentsHandler->addBody("return [");
        foreach ($options as $argument) {
            $type = match ($argument['mode']) {
                'Optional' => '::VALUE_OPTIONAL',
                'Required' => '::VALUE_REQUIRED',
                'Array' => '::VALUE_IS_ARRAY',
                'Negate-able' => '::VALUE_NEGATABLE',
                'None' => '::VALUE_NONE',
            };
            if (isset($argument['default'])) {
                $argumentsHandler->addBody("    ['". $argument['name'] ."', '". $argument['shortcut'] ."', ". 'InputOption'.$type .", '". $argument['description'] ."', '". $argument['default'] ."']");
            } else {
                $argumentsHandler->addBody("    ['". $argument['name'] ."', '". $argument['shortcut'] ."', ". 'InputOption'.$type .", '". $argument['description'] ."']");
            }
        }
        $argumentsHandler->addBody("];");
    }

    private function addHandler($klass, $options, $arguments): void
    {
        $handler = $klass->addMethod('handle')
            ->setPublic()
            ->setReturnType('int')
            ->addComment("Execute the console command.");

        foreach ($options as $option) {
            $optionName = $option['name'] ?? null;
            if ($optionName) $handler->addBody("\$$optionName = \$this->option('$optionName');");
        }

        foreach ($arguments as $argument) {
            $argumentName = $argument['name']?? null;
            if ($argumentName) $handler->addBody("\$$argumentName = \$this->argument('$argumentName');");
        }

        $handler->addBody(" // Add your logic here");

        $handler->addBody("return Command::SUCCESS;");
    }
}
