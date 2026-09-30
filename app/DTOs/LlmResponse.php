<?php

namespace App\DTOs;

class LlmResponse
{
    /**
     * @param  ToolCallRequest[]  $toolCalls
     * @param  array<string, mixed>|null  $usage
     */
    public function __construct(
        public readonly string $content,
        public readonly ?string $thinking = null,
        public readonly array $toolCalls = [],
        public readonly ?array $usage = null,
    ) {}

    public function isFinal(): bool
    {
        return $this->toolCalls === [];
    }
}
