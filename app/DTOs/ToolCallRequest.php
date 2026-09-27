<?php

namespace App\DTOs;

class ToolCallRequest
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  string|null  $thoughtSignature  Opaque token some providers (Gemini 3) attach to a call and reject the next turn without.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
        public readonly ?string $thoughtSignature = null,
    ) {}
}
