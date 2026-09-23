<?php

namespace App\Services\AgentLoop\Tools\World;

class WanderTool extends WorldTool
{
    public function name(): string
    {
        return 'wander';
    }

    public function description(): string
    {
        return 'Wanders around for a while with no particular goal, exploring and seeing what catches your interest, stopping here and there. Give a place to wander inside it, or none to roam around where you are. In the water it swims you around the pool.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'place' => ['type' => 'string', 'enum' => collect($this->toolbox->zones())->pluck('id')->all(), 'description' => 'The id of a place to wander around in. Leave it out to roam around where you are.'],
            ],
        ];
    }

    public function handle(array $arguments): array
    {
        $written = trim((string) ($arguments['place'] ?? ''));
        $zone = $written !== '' ? $this->toolbox->findZone($written) : null;
        if ($written !== '' && $zone === null) {
            throw new \RuntimeException(sprintf('There is no place called "%s" here. Use one of the place ids from the wander tool, or leave it out.', $written));
        }

        $this->toolbox->choose(['verb' => 'wander', 'target' => $zone['id'] ?? null, 'activity' => null]);

        return ['status' => 'started', 'note' => $zone !== null ? "You wander around {$zone['name']}." : 'You wander around.'];
    }
}
