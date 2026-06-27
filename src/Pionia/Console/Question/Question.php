<?php

namespace Pionia\Console\Question;

class Question
{
    private mixed $autocompleterValues = null;

    /** @var callable|null */
    private $autocompleterCallback = null;

    private bool $hidden = false;

    private bool $hiddenFallback = true;

    public function __construct(
        private readonly string $question,
        private readonly mixed $default = null,
    ) {
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * @param array<int, string>|null $values
     */
    public function setAutocompleterValues(?array $values): static
    {
        $this->autocompleterValues = $values;

        return $this;
    }

    /**
     * @return array<int, string>|null
     */
    public function getAutocompleterValues(): ?array
    {
        return $this->autocompleterValues;
    }

    public function setAutocompleterCallback(callable $callback): static
    {
        $this->autocompleterCallback = $callback;

        return $this;
    }

    public function getAutocompleterCallback(): ?callable
    {
        return $this->autocompleterCallback;
    }

    public function setHidden(bool $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHiddenFallback(bool $fallback): static
    {
        $this->hiddenFallback = $fallback;

        return $this;
    }

    public function isHiddenFallback(): bool
    {
        return $this->hiddenFallback;
    }
}
