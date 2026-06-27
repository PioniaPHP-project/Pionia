<?php

namespace Pionia\Console\Output;

final class OutputFormatterStyle
{
    /** @var list<string> */
    private array $foreground = [];

    /** @var list<string> */
    private array $background = [];

    /** @var list<string> */
    private array $options = [];

    private static array $foregroundColors = [
        'black' => '30', 'red' => '31', 'green' => '32', 'yellow' => '33',
        'blue' => '34', 'magenta' => '35', 'cyan' => '36', 'white' => '37',
        'default' => '39', 'gray' => '90', 'bright-red' => '91', 'bright-green' => '92',
        'bright-yellow' => '93', 'bright-blue' => '94', 'bright-magenta' => '95', 'bright-cyan' => '96',
    ];

    private static array $backgroundColors = [
        'black' => '40', 'red' => '41', 'green' => '42', 'yellow' => '43',
        'blue' => '44', 'magenta' => '45', 'cyan' => '46', 'white' => '47', 'default' => '49',
    ];

    public function __construct(?string $foreground = null, ?string $background = null, array $options = [])
    {
        if ($foreground !== null) {
            $this->setForeground($foreground);
        }

        if ($background !== null) {
            $this->setBackground($background);
        }

        $this->options = $options;
    }

    public function setForeground(string $color): void
    {
        if (!isset(self::$foregroundColors[$color])) {
            throw new \InvalidArgumentException(sprintf('Invalid foreground color "%s".', $color));
        }

        $this->foreground = [self::$foregroundColors[$color]];
    }

    public function setBackground(string $color): void
    {
        if (!isset(self::$backgroundColors[$color])) {
            throw new \InvalidArgumentException(sprintf('Invalid background color "%s".', $color));
        }

        $this->background = [self::$backgroundColors[$color]];
    }

    public function apply(string $text): string
    {
        if ($this->foreground === [] && $this->background === [] && $this->options === []) {
            return $text;
        }

        return sprintf("\033[%sm%s\033[0m", implode(';', [...$this->foreground, ...$this->background, ...$this->options]), $text);
    }
}
