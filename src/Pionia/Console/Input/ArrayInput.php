<?php

namespace Pionia\Console\Input;

final class ArrayInput extends Input
{
    /** @var array<string, mixed> */
    private array $parameters;

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    protected function parse(): void
    {
        $this->parseTokens($tokens = $this->buildTokens());

        if (isset($this->parameters['command'])) {
            $this->arguments['command'] = (string) $this->parameters['command'];
        }
    }

    /**
     * @return list<string>
     */
    private function buildTokens(): array
    {
        $tokens = [];

        foreach ($this->parameters as $key => $value) {
            if ($key === 'command') {
                continue;
            }

            if (is_int($key)) {
                $tokens[] = (string) $value;

                continue;
            }

            $key = ltrim((string) $key, '-');

            if (is_bool($value)) {
                if ($value) {
                    $tokens[] = '--' . $key;
                }

                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $tokens[] = '--' . $key . '=' . (string) $item;
                }

                continue;
            }

            if ($value === null || $value === '') {
                $tokens[] = '--' . $key;

                continue;
            }

            $tokens[] = '--' . $key . '=' . (string) $value;
        }

        return $tokens;
    }

    public function getCommandName(): ?string
    {
        return isset($this->parameters['command']) ? (string) $this->parameters['command'] : null;
    }
}
