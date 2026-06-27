<?php

namespace Pionia\Console\Output;

final class OutputFormatter
{
    /** @var array<string, OutputFormatterStyle> */
    private array $styles = [];

    private bool $decorated;

    public function __construct(bool $decorated = true)
    {
        $this->decorated = $decorated;
        $this->setStyle('error', new OutputFormatterStyle('red'));
        $this->setStyle('info', new OutputFormatterStyle('green'));
        $this->setStyle('comment', new OutputFormatterStyle('yellow'));
        $this->setStyle('question', new OutputFormatterStyle('black', 'cyan'));
        $this->setStyle('warning', new OutputFormatterStyle('yellow'));
    }

    public function setDecorated(bool $decorated): void
    {
        $this->decorated = $decorated;
    }

    public function isDecorated(): bool
    {
        return $this->decorated;
    }

    public function setStyle(string $name, OutputFormatterStyle $style): void
    {
        $this->styles[$name] = $style;
    }

    public function hasStyle(string $name): bool
    {
        return isset($this->styles[$name]);
    }

    public function getStyle(string $name): OutputFormatterStyle
    {
        if (!isset($this->styles[$name])) {
            throw new \InvalidArgumentException(sprintf('Undefined style "%s".', $name));
        }

        return $this->styles[$name];
    }

    public function format(string $message): string
    {
        if (!$this->decorated) {
            return strip_tags($message);
        }

        return preg_replace_callback('/<([^>]+)>(.*?)<\/\\1>/s', function (array $matches): string {
            $style = $matches[1];
            if (!isset($this->styles[$style])) {
                return $matches[2];
            }

            return $this->styles[$style]->apply($matches[2]);
        }, $message) ?? strip_tags($message);
    }
}
