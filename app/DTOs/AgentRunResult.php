<?php

namespace App\DTOs;

class AgentRunResult
{
    /**
     * @param  array<int, array{name: string, arguments: array<string, mixed>, result: array<string, mixed>|null, error: string|null}>  $toolCalls
     * @param  array<int, array<string, mixed>>  $usage
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls,
        public readonly ?string $thinking = null,
        public readonly array $usage = [],
    ) {}
}
