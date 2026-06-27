<?php

namespace Pionia\Console\Concerns;

use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputOption;

trait HasParameters
{
    protected function specifyParameters(): void
    {
        foreach ($this->getArguments() as $arguments) {
            if ($arguments instanceof InputArgument) {
                $this->getDefinition()->addArgument($arguments);
            } else {
                $this->addArgument(...$arguments);
            }
        }

        foreach ($this->getOptions() as $options) {
            if ($options instanceof InputOption) {
                $this->getDefinition()->addOption($options);
            } else {
                $this->addOption(...$options);
            }
        }
    }

    protected function getArguments(): array
    {
        return [];
    }

    protected function getOptions(): array
    {
        return [];
    }
}
