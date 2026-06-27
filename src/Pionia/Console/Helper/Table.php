<?php

namespace Pionia\Console\Helper;

use Pionia\Console\Output\OutputInterface;

final class Table
{
    /** @var list<string> */
    private array $headers = [];

    /** @var list<list<string>> */
    private array $rows = [];

    private TableStyle $style;

    public function __construct(private readonly OutputInterface $output)
    {
        $this->style = new TableStyle();
    }

    /**
     * @param list<string> $headers
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @param list<list<string>> $rows
     */
    public function setRows(array $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * @param list<string> $row
     */
    public function addRow(array $row): static
    {
        $this->rows[] = $row;

        return $this;
    }

    public function setStyle(TableStyle|string $style): static
    {
        if (is_string($style)) {
            $style = match ($style) {
                'box' => (new TableStyle())->setHorizontalBorderChar('─')->setVerticalBorderChar('│')->setCrossingChar('┼'),
                default => new TableStyle(),
            };
        }

        $this->style = $style;

        return $this;
    }

    public function setColumnStyle(int $columnIndex, TableStyle $style): static
    {
        return $this;
    }

    public function render(): void
    {
        $rows = $this->rows;
        if ($this->headers !== []) {
            array_unshift($rows, $this->headers);
        }

        if ($rows === []) {
            return;
        }

        $widths = [];
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, strlen(strip_tags((string) $cell)));
            }
        }

        $line = fn (array $row, string $left, string $mid, string $right): string => $left
            . implode($mid, array_map(fn ($i) => ' ' . str_pad(strip_tags((string) ($row[$i] ?? '')), $widths[$i]) . ' ', array_keys($widths)))
            . $right;

        $border = fn (): string => $this->style->cross()
            . implode($this->style->cross(), array_map(fn ($w) => str_repeat($this->style->horizontal(), $w + 2), $widths))
            . $this->style->cross();

        $this->output->writeln($border());
        foreach ($rows as $index => $row) {
            $this->output->writeln($line($row, $this->style->vertical(), $this->style->vertical(), $this->style->vertical()));
            if ($index === 0 && $this->headers !== []) {
                $this->output->writeln($border());
            }
        }
        $this->output->writeln($border());
    }
}
