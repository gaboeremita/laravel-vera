<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;

/**
 * The answer to a judged question. Its call is read directly from the judge's reply.
 */
class JudgementTool implements AgentTool
{
    public function name(): string
    {
        return 'judgement';
    }

    public function description(): string
    {
        return 'Answers whether the question has been met, citing the messages that show it.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'met' => ['type' => 'boolean', 'description' => 'Whether the conversation shows the question has been met.'],
                'messageIds' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'The ids of the messages that show it.'],
                'reason' => ['type' => 'string', 'description' => 'One or two sentences on why.'],
            ],
            'required' => ['met', 'messageIds', 'reason'],
        ];
    }

    /**
     * @return array{met: bool, messageIds: array<int, int>, reason: string}
     */
    public function handle(array $arguments): array
    {
        return [
            'met' => (bool) ($arguments['met'] ?? false),
            'messageIds' => collect($arguments['messageIds'] ?? [])->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'reason' => trim((string) ($arguments['reason'] ?? '')),
        ];
    }

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    public function retryAttempts(): int
    {
        return 1;
    }
}
