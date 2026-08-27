<?php

namespace App\Contracts;

use App\DTOs\LlmResponse;
use App\Models\AiModel;

interface LlmProvider
{
    /**
     * Send a chat request to the LLM and return a unified response.
     *
     * A message may additionally carry `tool_calls` (assistant turn requesting
     * one or more tools, shape `array{id: string, name: string, arguments: array}[]`)
     * or, for `role: 'tool'`, `tool_call_id` and `content` holding that call's result.
     * Each provider translates these normalized turns into its own wire format.
     *
     * @param  array<int, array{role: string, content: string|null, images?: array, tool_calls?: array, tool_call_id?: string}>  $messages
     * @param  array<int, array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     */
    public function chat(array $messages, array $options = [], array $tools = []): LlmResponse;

    public static function fromModel(AiModel $aiModel): static;
}
