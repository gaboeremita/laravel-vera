<?php

namespace App\DTOs;

class TermRule
{
    /**
     * @param  list<string>  $sourceVariants
     * @param  list<string>  $targetVariants
     */
    public function __construct(
        public readonly string $source,
        public readonly array $sourceVariants,
        public readonly string $target,
        public readonly array $targetVariants,
        public readonly bool $invariant,
        public readonly bool $caseSensitive,
        public readonly int $line,
    ) {}

    /**
     * @return list<string>
     */
    public function sourceTerms(): array
    {
        return [$this->source, ...$this->sourceVariants];
    }

    /**
     * @return list<string>
     */
    public function targetTerms(): array
    {
        return [$this->target, ...$this->targetVariants];
    }
}
