<?php

namespace Pionia\Console;

use Pionia\Contracts\NewLineAware;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class OutputStyle extends SymfonyStyle implements NewLineAware
{
    /**
     * The output instance.
     */
    private OutputInterface $output;

    /**
     * The number of trailing new lines written by the last output.
     *
     * This is initialized as 1 to account for the new line written by the shell after executing a command.
     */
    protected int $newLinesWritten = 1;

    /**
     * Create a new Console OutputStyle instance.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return void
     */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        parent::__construct($input, $output);
    }

    public function askQuestion(Question $question): mixed
    {
        try {
            return parent::askQuestion($question);
        } finally {
            $this->newLinesWritten++;
        }
    }

    /**
     */
    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        $this->newLinesWritten = $this->trailingNewLineCount($messages) + (int) $newline;

        parent::write($messages, $newline, $options);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function writeln(string|iterable $messages, int $type = self::OUTPUT_NORMAL): void
    {
        $this->newLinesWritten = $this->trailingNewLineCount($messages) + 1;

        parent::writeln($messages, $type);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function newLine(int $count = 1): void
    {
        $this->newLinesWritten += $count;

        parent::newLine($count);
    }

    /**
     * {@inheritdoc}
     */
    public function newLinesWritten(): int
    {
        if ($this->output instanceof static) {
            return $this->output->newLinesWritten();
        }

        return $this->newLinesWritten;
    }

    /*
     * Count the number of trailing new lines in a string.
     *
     * @param  string|iterable  $messages
     */
    protected function trailingNewLineCount($messages): int
    {
        if (is_iterable($messages)) {
            $string = '';

            foreach ($messages as $message) {
                $string .= $message.PHP_EOL;
            }
        } else {
            $string = $messages;
        }

        return strlen($string) - strlen(rtrim($string, PHP_EOL));
    }

    /**
     * Returns whether verbosity is quiet (-q).
     *
     * @return bool
     */
    public function isQuiet(): bool
    {
        return $this->output->isQuiet();
    }

    /**
     * Returns whether verbosity is verbose (-v).
     *
     * @return bool
     */
    public function isVerbose(): bool
    {
        return $this->output->isVerbose();
    }

    /**
     * Returns whether verbosity is very verbose (-vv).
     *
     * @return bool
     */
    public function isVeryVerbose(): bool
    {
        return $this->output->isVeryVerbose();
    }

    /**
     * Returns whether verbosity is debug (-vvv).
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->output->isDebug();
    }

    /**
     * Get the underlying Symfony output implementation.
     */
    public function getOutput(): OutputInterface
    {
        return $this->output;
    }
}
