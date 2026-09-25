<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;

class StopConversationTool implements AgentTool
{
    public bool $stopped = false;

    public function name(): string
    {
        return 'stop_conversation';
    }

    public function description(): string
    {
        return 'Stops the conversation you are having in person so you can pick it up another time. Say your goodbye in your reply as well.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function handle(array $arguments): array
    {
        $this->stopped = true;

        return ['status' => 'stopped', 'note' => 'The conversation stops once you have said your line, and you can pick it up another time.'];
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
