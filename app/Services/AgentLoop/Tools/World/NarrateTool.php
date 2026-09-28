<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;

/**
 * The narrator's verdict. Its call is read directly from the narrator's reply.
 */
class NarrateTool implements AgentTool
{
    public function name(): string
    {
        return 'narrate';
    }

    public function description(): string
    {
        return 'Gives your verdict on whether the attempt succeeds, and the narration of what happens.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'succeeded' => ['type' => 'boolean', 'description' => 'Whether the requirement is met and the attempt succeeds.'],
                'narration' => ['type' => 'string', 'description' => 'Two to four sentences, in second person, describing what happens.'],
            ],
            'required' => ['succeeded', 'narration'],
        ];
    }

    public function handle(array $arguments): array
    {
        return ['succeeded' => (bool) ($arguments['succeeded'] ?? false), 'narration' => trim((string) ($arguments['narration'] ?? ''))];
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
