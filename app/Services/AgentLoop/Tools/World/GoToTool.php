<?php

namespace App\Services\AgentLoop\Tools\World;

class GoToTool extends WorldTool
{
    public function name(): string
    {
        return 'go_to';
    }

    public function description(): string
    {
        return 'Walks you to a place or thing in this world, or to the user with target "user". In the water, going to the user swims you to the side of the pool nearest them, where you rest at the edge. You start moving when your reply is shown; your recent activity later tells you whether you arrived.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'target' => ['type' => 'string', 'enum' => [WorldToolbox::USER_TARGET, ...$this->toolbox->targetIds()], 'description' => 'The id of the place, thing or spot to walk to, or "user" to go to the user.'],
            ],
            'required' => ['target'],
        ];
    }

    public function handle(array $arguments): array
    {
        $written = (string) ($arguments['target'] ?? '');
        if ($this->toolbox->sameName(WorldToolbox::USER_TARGET, $written)) {
            $this->toolbox->choose(['verb' => 'go_to', 'target' => WorldToolbox::USER_TARGET, 'activity' => null]);

            return ['status' => 'started', 'note' => 'You are going to the user.'];
        }

        $target = $this->toolbox->findTarget($written);
        if ($target === null) {
            throw new \RuntimeException(sprintf('There is no place, thing or spot called "%s" here. Use one of the ids from the go_to tool.', $written));
        }

        $this->toolbox->choose(['verb' => 'go_to', 'target' => $target['id'], 'activity' => null]);

        return ['status' => 'started', 'note' => "You are walking to {$target['name']}."];
    }
}
