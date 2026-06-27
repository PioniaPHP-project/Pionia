<?php

namespace Pionia\Console\Concerns;

use Pionia\Console\Command;
use Pionia\Console\Helper\Table;
use Pionia\Console\Helper\TableStyle;
use Pionia\Console\Input\InputInterface;
use Pionia\Console\Output\OutputFormatterStyle;
use Pionia\Console\Output\OutputInterface;
use Pionia\Console\OutputStyle;
use Pionia\Console\Question\ChoiceQuestion;
use Pionia\Console\Question\Question;
use Pionia\Collections\Arrayable;
use Closure;

trait InteractsWithIO
{
    protected InputInterface $input;

    protected OutputStyle $output;

    protected int $verbosity = OutputInterface::VERBOSITY_NORMAL;

    protected array $verbosityMap = [
        'v' => OutputInterface::VERBOSITY_VERBOSE,
        'vv' => OutputInterface::VERBOSITY_VERY_VERBOSE,
        'vvv' => OutputInterface::VERBOSITY_DEBUG,
        'quiet' => OutputInterface::VERBOSITY_QUIET,
        'normal' => OutputInterface::VERBOSITY_NORMAL,
    ];

    public function hasArgument(int|string $name): bool
    {
        return $this->input->hasArgument($name);
    }

    public function argument(?string $key = null): bool|array|string|null
    {
        if (is_null($key)) {
            return $this->input->getArguments();
        }

        return $this->input->getArgument($key);
    }

    public function arguments(): array
    {
        return $this->argument();
    }

    public function hasOption(string $name): bool
    {
        return $this->input->hasOption($name);
    }

    public function option(?string $key = null): bool|array|string|null
    {
        if (is_null($key)) {
            return $this->input->getOptions();
        }

        return $this->input->getOption($key);
    }

    public function options(): array
    {
        return $this->option();
    }

    public function confirm(string $question, bool $default = false): bool
    {
        return $this->output->confirm($question, $default);
    }

    public function ask(string $question, ?string $default = null): mixed
    {
        return $this->output->ask($question, $default);
    }

    public function anticipate(string $question, callable|array $choices, ?string $default = null): mixed
    {
        return $this->askWithCompletion($question, $choices, $default);
    }

    public function askWithCompletion(string $question, callable|array $choices, ?string $default = null): mixed
    {
        $question = new Question($question, $default);

        is_callable($choices)
            ? $question->setAutocompleterCallback($choices)
            : $question->setAutocompleterValues($choices);

        return $this->output->askQuestion($question);
    }

    public function secret(string $question, bool $fallback = true): mixed
    {
        $question = new Question($question);
        $question->setHidden(true)->setHiddenFallback($fallback);

        return $this->output->askQuestion($question);
    }

    public function choice(string $question, array $choices, int|string|null $default = null, mixed $attempts = null, bool $multiple = false): array|string
    {
        $question = new ChoiceQuestion($question, $choices, $default);
        $question->setMaxAttempts($attempts)->setMultiselect($multiple);

        return $this->output->askQuestion($question);
    }

    public function table(array $headers, array|Arrayable $rows, TableStyle|string $tableStyle = 'default', array $columnStyles = []): void
    {
        $table = new Table($this->output);

        if ($rows instanceof Arrayable) {
            $rows = $rows->toArray();
        }

        $table->setHeaders($headers)->setRows($rows)->setStyle($tableStyle);

        foreach ($columnStyles as $columnIndex => $columnStyle) {
            $table->setColumnStyle($columnIndex, $columnStyle);
        }

        $table->render();
    }

    public function withProgressBar(iterable|int $totalSteps, Closure $callback)
    {
        $bar = $this->output->createProgressBar(
            is_iterable($totalSteps) ? count((array) $totalSteps) : $totalSteps,
        );

        $bar->start();

        if (is_iterable($totalSteps)) {
            foreach ($totalSteps as $value) {
                $callback($value, $bar);
                $bar->advance();
            }
        } else {
            $callback($bar);
        }

        $bar->finish();

        if (is_iterable($totalSteps)) {
            return $totalSteps;
        }
    }

    public function info(string $string, string|int $verbosity = 'normal'): void
    {
        $this->line($string, 'info', $verbosity);
    }

    public function line(string $string, ?string $style = null, int|string|null $verbosity = 'normal'): void
    {
        $styled = $style ? "<$style>$string</$style>" : $string;
        $this->output->writeln($styled, $this->parseVerbosity($verbosity));
    }

    public function comment(string $string, int|string|null $verbosity = null): void
    {
        $this->line($string, 'comment', $verbosity);
    }

    public function question(string $string, int|string|null $verbosity = null): void
    {
        $this->line($string, 'question', $verbosity);
    }

    public function error(string $string, int|string|null $verbosity = null): void
    {
        $this->line($string, 'error', $verbosity);
    }

    public function warn(string $string, int|string|null $verbosity = null): void
    {
        if (! $this->output->getFormatter()->hasStyle('warning')) {
            $this->output->getFormatter()->setStyle('warning', new OutputFormatterStyle('yellow'));
        }

        $this->line($string, 'warning', $verbosity);
    }

    public function alert(string $string, int|string|null $verbosity = null): void
    {
        $length = strlen(strip_tags($string)) + 12;
        $this->comment(str_repeat('*', $length), $verbosity);
        $this->comment('*     '.$string.'     *', $verbosity);
        $this->comment(str_repeat('*', $length), $verbosity);
        $this->comment('', $verbosity);
    }

    public function newLine(int $count = 1): static
    {
        $this->output->newLine($count);

        return $this;
    }

    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    public function setOutput(OutputStyle $output): void
    {
        $this->output = $output;
    }

    protected function setVerbosity(int|string $level): void
    {
        $this->verbosity = $this->parseVerbosity($level);
    }

    protected function parseVerbosity(int|string|null $level = 'normal'): int
    {
        if ($level === null) {
            $level = 'normal';
        }

        if (isset($this->verbosityMap[$level])) {
            $level = $this->verbosityMap[$level];
        } elseif (! is_int($level)) {
            $level = $this->verbosity;
        }

        return $level;
    }

    public function getOutput(): OutputStyle
    {
        return $this->output;
    }
}
