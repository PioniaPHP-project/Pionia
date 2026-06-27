<?php

namespace Pionia\Console\Question;

class ChoiceQuestion extends Question
{
    private ?int $maxAttempts = null;

    private bool $multiselect = false;

    /**
     * @param array<int|string, string> $choices
     */
    public function __construct(
        string $question,
        private readonly array $choices,
        mixed $default = null,
    ) {
        parent::__construct($question, $default);
    }

    /**
     * @return array<int|string, string>
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    public function setMaxAttempts(?int $maxAttempts): static
    {
        $this->maxAttempts = $maxAttempts;

        return $this;
    }

    public function getMaxAttempts(): ?int
    {
        return $this->maxAttempts;
    }

    public function setMultiselect(bool $multiselect): static
    {
        $this->multiselect = $multiselect;

        return $this;
    }

    public function isMultiselect(): bool
    {
        return $this->multiselect;
    }
}
