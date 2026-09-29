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
                'action' => ['type' => 'string', 'description' => 'What someone watching sees them do, as a short stage direction under fifteen words, starting with a verb in the third person, e.g. "leans over the counter and orders four al pastor, extra salsa".'],
            ],
            'required' => ['succeeded', 'narration', 'action'],
        ];
    }

    public function handle(array $arguments): array
    {
        return [
            'succeeded' => (bool) ($arguments['succeeded'] ?? false),
            'narration' => trim((string) ($arguments['narration'] ?? '')),
            'action' => trim(trim((string) ($arguments['action'] ?? '')), '*. ') ?: null,
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
