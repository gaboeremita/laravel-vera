<?php

namespace App\Services\AgentLoop\Tools\World;

class RememberTool extends WorldTool
{
    public function name(): string
    {
        return 'remember';
    }

    public function description(): string
    {
        return 'A memory comes back to you, right where you are: something from your time with the user, a conversation with someone here, or something from your archive. You stay as you are; your line carries what the memory stirs in you.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function handle(array $arguments): array
    {
        $memory = $this->toolbox->recallMemory();
        if ($memory === null) {
            throw new \RuntimeException('Nothing comes back to you right now; think about something instead.');
        }

        $this->toolbox->choose(['verb' => 'remember', 'target' => mb_substr($memory['from'], 0, 255), 'activity' => null]);

        return ['status' => 'started', 'from' => $memory['from'], 'memory' => $memory['memory']];
    }
}
