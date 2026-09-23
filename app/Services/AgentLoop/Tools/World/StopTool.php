<?php

namespace App\Services\AgentLoop\Tools\World;

class StopTool extends WorldTool
{
    public function name(): string
    {
        return 'stop';
    }

    public function description(): string
    {
        return 'Stops you where you are, ending any walking or following.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function handle(array $arguments): array
    {
        $this->toolbox->choose(['verb' => 'stop', 'target' => null, 'activity' => null]);

        return ['status' => 'started', 'note' => 'You stopped where you are.'];
    }
}
