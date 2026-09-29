<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;

/**
 * The review's verdict on a reveal. Its call is read directly from the reviewer's reply.
 */
class VerdictTool implements AgentTool
{
    public function name(): string
    {
        return 'verdict';
    }

    public function description(): string
    {
        return 'Gives your verdict on whether the character shares the secret now.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'approved' => ['type' => 'boolean', 'description' => 'Whether the situation reasonably meets when they share it.'],
                'verdict' => ['type' => 'string', 'description' => 'One sentence on why.'],
            ],
            'required' => ['approved', 'verdict'],
        ];
    }

    public function handle(array $arguments): array
    {
        return [
            'approved' => (bool) ($arguments['approved'] ?? false),
            'verdict' => trim((string) ($arguments['verdict'] ?? '')),
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
