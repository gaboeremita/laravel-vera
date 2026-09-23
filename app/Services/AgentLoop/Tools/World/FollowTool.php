<?php

namespace App\Services\AgentLoop\Tools\World;

class FollowTool extends WorldTool
{
    public function name(): string
    {
        return 'follow';
    }

    public function description(): string
    {
        return 'Makes you follow the user around this world until you stop or go somewhere else.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function handle(array $arguments): array
    {
        $this->toolbox->choose(['verb' => 'follow', 'target' => null, 'activity' => null]);

        return ['status' => 'started', 'note' => 'You are following the user.'];
    }
}
