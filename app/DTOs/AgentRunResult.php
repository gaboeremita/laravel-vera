<?php

namespace App\DTOs;

class AgentRunResult
{
    /**
     * @param  array<int, array{name: string, arguments: array<string, mixed>, result: array<string, mixed>|null, error: string|null}>  $toolCalls
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls,
    ) {}
}
