<?php

namespace App\DTOs;

class LlmResponse
{
    /**
     * @param  ToolCallRequest[]  $toolCalls
     */
    public function __construct(
        public readonly string $content,
        public readonly ?string $thinking = null,
        public readonly array $toolCalls = [],
    ) {}

    public function isFinal(): bool
    {
        return $this->toolCalls === [];
    }
}
