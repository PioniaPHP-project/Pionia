<?php

namespace Pionia\Console\Helper;

use Pionia\Console\Output\OutputInterface;

final class ProgressBar
{
    private int $step = 0;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly int $max = 0,
    ) {
    }

    public function start(int $max = 0): void
    {
        $this->step = 0;
    }

    public function advance(int $step = 1): void
    {
        $this->step += $step;
        $this->render();
    }

    public function finish(): void
    {
        $this->output->writeln('');
    }

    private function render(): void
    {
        $max = max(1, $this->max);
        $percent = min(100, (int) floor(($this->step / $max) * 100));
        $width = 30;
        $filled = (int) floor($width * ($percent / 100));
        $bar = str_repeat('=', max(0, $filled - 1)) . '>' . str_repeat(' ', max(0, $width - $filled));
        $this->output->write("\r<info>[$bar]</info> $percent%");
    }
}
