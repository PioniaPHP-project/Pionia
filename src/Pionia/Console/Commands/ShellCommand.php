<?php

namespace Pionia\Console\Commands;

use Pionia\Console\Command;

/**
 * Interactive Pionia shell — evaluate PHP with full app context.
 */
final class ShellCommand extends Command
{
    protected string $name = 'shell';

    protected array $aliases = ['tinker', 'repl', 'pionia:shell'];

    protected string $description = 'Start an interactive Pionia shell';

    protected string $help = 'Evaluate PHP in a REPL with app(), realm(), env(), and logger() available. Type exit to quit.';

    /** @var list<string> */
    private array $history = [];

    protected function handle(): int
    {
        if (!$this->input->isInteractive()) {
            $this->error('Shell requires an interactive terminal. Do not pass --no-interaction.');

            return Command::FAILURE;
        }

        if (!function_exists('readline') && (!defined('STDIN') || !@stream_isatty(STDIN))) {
            $this->error('Shell requires an interactive terminal (TTY).');

            return Command::FAILURE;
        }

        $this->info('Pionia interactive shell');
        $this->comment('Helpers: app(), realm(), env(), logger(), cache()');
        $this->comment('Meta: help, exit, clear, :history');
        $this->newLine();

        while (true) {
            $line = $this->readLine('pionia> ');
            if ($line === false || trim($line) === '') {
                continue;
            }

            $trimmed = trim($line);
            if (in_array(strtolower($trimmed), ['exit', 'quit', ':q'], true)) {
                $this->info('Bye.');

                return Command::SUCCESS;
            }

            if (in_array(strtolower($trimmed), ['help', '?'], true)) {
                $this->renderHelp();

                continue;
            }

            if (strtolower($trimmed) === 'clear') {
                $this->write("\033[2J\033[H");

                continue;
            }

            if (strtolower($trimmed) === ':history') {
                foreach ($this->history as $index => $entry) {
                    $this->line(sprintf('% 4d  %s', $index + 1, $entry));
                }

                continue;
            }

            $this->history[] = $line;
            $this->evaluate($line);
        }
    }

    private function renderHelp(): void
    {
        $this->table(
            ['Topic', 'Details'],
            [
                ['Expressions', 'Any valid PHP expression or statement'],
                ['app()', 'Application realm / container'],
                ['realm()', 'Alias for app()'],
                ['env($key)', 'Read environment value'],
                ['logger()', 'PSR-3 logger'],
                ['cache()', 'CacheManager'],
                ['exit', 'Leave the shell'],
            ],
        );
    }

    private function evaluate(string $code): void
    {
        try {
            if (!str_contains($code, ';') && !str_starts_with(trim($code), 'return ')) {
                $result = eval('return ' . $code . ';');
            } else {
                $result = eval($code . (str_ends_with(rtrim($code), ';') ? '' : ';'));
            }

            if (isset($result) && $result !== null) {
                $this->dump($result);
            }
        } catch (\ParseError $e) {
            try {
                eval($code . ';');
            } catch (\Throwable $inner) {
                $this->error('Parse error: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->comment($e->getFile() . ':' . $e->getLine());
        }
    }

    private function dump(mixed $value): void
    {
        if (is_string($value)) {
            $this->line($value);

            return;
        }

        if (is_bool($value)) {
            $this->line($value ? 'true' : 'false');

            return;
        }

        if ($value === null) {
            $this->comment('null');

            return;
        }

        $this->line(print_r($value, true));
    }

    private function readLine(string $prompt): string|false
    {
        if (function_exists('readline')) {
            $line = readline($prompt);
            if (is_string($line) && $line !== '') {
                readline_add_history($line);
            }

            return $line;
        }

        $this->write($prompt);

        $line = fgets(STDIN);

        return $line === false ? false : rtrim($line, "\r\n");
    }

    private function write(string $message): void
    {
        $this->output->write($message);
    }
}
