<?php

namespace App\Services\AgentLoop\Tools\World;

class SwimToEdgeTool extends WorldTool
{
    public function name(): string
    {
        return 'swim_to_edge';
    }

    public function description(): string
    {
        return 'While you are in the water, swims you to the nearest side of the pool, where you rest at the edge until you move again.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function handle(array $arguments): array
    {
        $this->toolbox->choose(['verb' => 'swim_to_edge', 'target' => null, 'activity' => null]);

        return ['status' => 'started', 'note' => 'You swim to the side of the pool.'];
    }
}
