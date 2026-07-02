<?php

namespace Pionia\Console;

use Pionia\Console\Helper\ProgressBar;
use Pionia\Console\Input\InputInterface;
use Pionia\Console\Output\OutputInterface;
use Pionia\Console\Question\ChoiceQuestion;
use Pionia\Console\Question\Question;
use Pionia\Contracts\NewLineAware;

class OutputStyle implements OutputInterface, NewLineAware
{
    public const OUTPUT_NORMAL = Output\OutputInterface::OUTPUT_NORMAL;

    private OutputInterface $output;

    protected int $newLinesWritten = 1;

    public function __construct(
        private readonly InputInterface $input,
        OutputInterface $output,
    ) {
        $this->output = $output;
    }

    public function askQuestion(Question $question): mixed
    {
        if (!$this->input->isInteractive()) {
            return $question->getDefault();
        }

        if ($question instanceof ChoiceQuestion) {
            return $this->askChoice($question);
        }

        $prompt = $question->getQuestion();
        $default = $question->getDefault();
        if ($default !== null && $default !== '') {
            $prompt .= ' [' . $default . ']';
        }
        $prompt .= ': ';

        if ($question->isHidden()) {
            $this->write($prompt);
            $answer = $this->readHidden();

            return $answer !== '' ? $answer : $default;
        }

        $answer = $this->readLine($prompt);

        return $answer !== '' ? $answer : $default;
    }

    public function confirm(string $question, bool $default = false): bool
    {
        $suffix = $default ? '[Y/n]' : '[y/N]';
        $answer = strtolower((string) $this->ask($question . ' ' . $suffix, $default ? 'yes' : 'no'));

        return in_array($answer, ['y', 'yes', '1', 'true'], true);
    }

    public function ask(string $question, ?string $default = null): mixed
    {
        return $this->askQuestion(new Question($question, $default));
    }

    public function createProgressBar(int $max = 0): ProgressBar
    {
        return new ProgressBar($this, $max);
    }

    public function write(string|iterable $messages, bool $newline = false, int $options = self::OUTPUT_NORMAL): void
    {
        $this->newLinesWritten = $this->trailingNewLineCount($messages) + (int) $newline;
        $this->output->write($messages, $newline, $options);
    }

    public function writeln(string|iterable $messages, int $type = self::OUTPUT_NORMAL): void
    {
        $this->newLinesWritten = $this->trailingNewLineCount($messages) + 1;
        $this->output->writeln($messages, $type);
    }

    public function newLine(int $count = 1): void
    {
        $this->newLinesWritten += $count;
        for ($i = 0; $i < $count; $i++) {
            $this->output->writeln('');
        }
    }

    public function newLinesWritten(): int
    {
        return $this->newLinesWritten;
    }

    public function isQuiet(): bool
    {
        return $this->output->isQuiet();
    }

    public function isVerbose(): bool
    {
        return $this->output->isVerbose();
    }

    public function isVeryVerbose(): bool
    {
        return $this->output->isVeryVerbose();
    }

    public function isDebug(): bool
    {
        return $this->output->isDebug();
    }

    public function getFormatter(): Output\OutputFormatter
    {
        return $this->output->getFormatter();
    }

    public function setVerbosity(int $level): void
    {
        $this->output->setVerbosity($level);
    }

    public function getVerbosity(): int
    {
        return $this->output->getVerbosity();
    }

    public function setDecorated(bool $decorated): void
    {
        $this->output->setDecorated($decorated);
    }

    public function isDecorated(): bool
    {
        return $this->output->isDecorated();
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    private function askChoice(ChoiceQuestion $question): string|array
    {
        $choices = $question->getChoices();
        $labels = array_values($choices);
        $maxAttempts = $question->getMaxAttempts() ?? 3;
        $default = $question->getDefault();
        $defaultLabel = is_string($default) ? $default : (is_int($default) ? ($labels[$default] ?? null) : null);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            foreach ($labels as $index => $label) {
                $this->writeln(sprintf('  [%d] %s', $index + 1, $label));
            }

            $defaultIndex = $defaultLabel !== null ? array_search($defaultLabel, $labels, true) : false;
            $suffix = $defaultIndex !== false ? ' [' . ((int) $defaultIndex + 1) . ']' : '';
            $answer = trim($this->readLine($question->getQuestion() . $suffix . ': '));

            if ($answer === '' && $defaultLabel !== null) {
                return $defaultLabel;
            }

            if ($question->isMultiselect()) {
                return array_map(fn ($i) => $labels[(int) $i] ?? $i, explode(',', $answer));
            }

            if (ctype_digit($answer)) {
                $index = (int) $answer - 1;
                if (isset($labels[$index])) {
                    return $labels[$index];
                }
            }

            $lower = strtolower($answer);
            if (in_array($lower, $labels, true)) {
                return $lower;
            }

            $this->writeln('<error>Invalid choice. Pick a number from the list.</error>');
        }

        return $defaultLabel ?? $labels[0];
    }

    private function readLine(string $prompt): string
    {
        if (function_exists('readline')) {
            $line = readline($prompt);

            return is_string($line) ? trim($line) : '';
        }

        $this->write($prompt);
        $line = fgets(STDIN);

        return $line === false ? '' : trim($line);
    }

    private function readHidden(): string
    {
        if (PHP_OS_FAMILY !== 'Windows' && shell_exec('which stty') !== null) {
            shell_exec('stty -echo');
            $line = trim((string) fgets(STDIN));
            shell_exec('stty echo');
            $this->newLine();

            return $line;
        }

        return trim((string) fgets(STDIN));
    }

    protected function trailingNewLineCount(string|iterable $messages): int
    {
        if (is_iterable($messages)) {
            $string = implode(PHP_EOL, iterator_to_array($messages));
        } else {
            $string = $messages;
        }

        return strlen($string) - strlen(rtrim($string, PHP_EOL));
    }
}
