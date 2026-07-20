<?php

namespace Pionia\Scaffolding;

use Composer\Script\Event;
use Pionia\Utils\Support;

/**
 * Runs after composer create-project: rename app from folder, optional frontend scaffold.
 *
 * Usage:
 *   composer create-project pionia/pionia-app my-api -- --vue-ts
 *   PIONIA_WITH_FRONTEND=vue-ts composer create-project pionia/pionia-app my-api
 */
final class PostCreateProjectHandler
{
    public static function handle(Event $event): void
    {
        $projectRoot = getcwd() ?: '.';

        self::renameAppFromDirectory($projectRoot);

        $framework = self::resolveFramework($event->getArguments());
        if ($framework === null) {
            return;
        }

        $io = $event->getIO();
        $io->write("<info>Scaffolding {$framework} frontend …</info>");

        $pionia = $projectRoot . DIRECTORY_SEPARATOR . 'pionia';
        $command = 'php ' . escapeshellarg($pionia)
            . ' frontend:scaffold --framework=' . escapeshellarg($framework)
            . ' --yes';

        $process = \Pionia\Process\Process::fromShellCommandline($command, $projectRoot);
        $process->setTimeout(900);
        $process->run(function ($type, $buffer) use ($io): void {
            $io->write($buffer, false);
        });

        if (!$process->isSuccessful()) {
            $io->writeError('<error>Frontend scaffold failed — run: php pionia frontend:scaffold</error>');

            return;
        }

        $io->write('<info>Frontend ready.</info> Dev: <comment>php pionia frontend:dev</comment>');
    }

    /**
     * @param list<mixed> $composerArgs Extra args from: composer create-project … -- --vue-ts
     */
    public static function resolveFramework(array $composerArgs): ?string
    {
        $tokens = [];
        foreach ($composerArgs as $arg) {
            if (is_string($arg)) {
                $tokens[] = $arg;
            }
        }

        $env = getenv('PIONIA_WITH_FRONTEND');
        if (is_string($env) && $env !== '') {
            $tokens[] = $env;
        }

        $resolved = FrontendFramework::resolveFromTokens($tokens);
        if ($resolved !== null) {
            return $resolved;
        }

        if (!self::isInteractive()) {
            return null;
        }

        if (!self::askYesNo('Scaffold a Vite frontend in frontend/? [y/N] ')) {
            return null;
        }

        return FrontendFramework::chooseFromTerminal();
    }

    private static function renameAppFromDirectory(string $appDir): void
    {
        $file = $appDir . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        $name = Support::titleize(basename($appDir));
        $out = [];

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), 'APP_NAME=')) {
                $out[] = 'APP_NAME="' . addslashes($name) . '"';
            } else {
                $out[] = $line;
            }
        }

        file_put_contents($file, implode(PHP_EOL, $out) . PHP_EOL);
    }

    private static function isInteractive(): bool
    {
        return function_exists('posix_isatty') && @posix_isatty(STDIN);
    }

    private static function askYesNo(string $question): bool
    {
        fwrite(STDOUT, $question);
        $answer = fgets(STDIN);

        return is_string($answer) && in_array(strtolower(trim($answer)), ['y', 'yes'], true);
    }
}
