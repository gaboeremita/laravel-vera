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
        return 'Walks you to a place or thing in this world. You start walking when your reply is shown; your recent activity later tells you whether you arrived.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'target' => ['type' => 'string', 'enum' => $this->toolbox->targetIds(), 'description' => 'The id of the place or thing to walk to.'],
            ],
            'required' => ['target'],
        ];
    }

    public function handle(array $arguments): array
    {
        $written = (string) ($arguments['target'] ?? '');
        $target = $this->toolbox->findZone($written) ?? $this->toolbox->findObject($written);
        if ($target === null) {
            throw new \RuntimeException(sprintf('There is no place or thing called "%s" here. Use one of the ids from the go_to tool.', $written));
        }

        $this->toolbox->choose(['verb' => 'go_to', 'target' => $target['id'], 'activity' => null]);

        return ['status' => 'started', 'note' => "You are walking to {$target['name']}."];
    }
}
