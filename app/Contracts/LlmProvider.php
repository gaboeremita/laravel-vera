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
     * `content` may also be a list of text parts. A part with `cachePoint` marks
     * the end of a reusable request start; the provider sends that mark only when
     * its model is set to send cache marks, and otherwise joins the parts' text.
     * `$conversationKey` is sent under the model's conversation key field, if any.
     *
     * @param  array<int, array{role: string, content: string|null|list<array{type: 'text', text: string, cachePoint?: bool}>, images?: array, tool_calls?: array, tool_call_id?: string}>  $messages
     * @param  array<int, array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     */
    public function chat(array $messages, array $options = [], array $tools = [], ?string $conversationKey = null): LlmResponse;

    public static function fromModel(AiModel $aiModel): static;
}
