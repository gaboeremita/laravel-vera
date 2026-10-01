<?php

namespace App\DTOs;

class PromptLayout
{
    /**
     * @param  list<string>  $occasionalParts
     */
    public function __construct(
        private readonly string $unchanging,
        private readonly array $occasionalParts,
        private readonly string $turn,
    ) {}

    public function unchanging(): string
    {
        return $this->unchanging;
    }

    /**
     * @return list<string>
     */
    public function occasionalParts(): array
    {
        return $this->occasionalParts;
    }

    public function turn(): string
    {
        return $this->turn;
    }

    public function fullText(): string
    {
        return implode("\n\n", array_filter([$this->unchanging, ...$this->occasionalParts, $this->turn], fn (string $part) => $part !== ''));
    }
}
