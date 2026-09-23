<?php

namespace App\Services\AgentLoop\Tools\World;

class UseTool extends WorldTool
{
    public function name(): string
    {
        return 'use';
    }

    public function description(): string
    {
        return 'Walks you to a spot on a thing, such as a stool or a lounger, and does an activity there, taking the posture it needs. You stay there until you go somewhere else. where_can_i and what_is_in tell you which activities each spot offers.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'spot' => ['type' => 'string', 'enum' => collect($this->toolbox->spots())->pluck('id')->all(), 'description' => 'The id of the spot.'],
                'activity' => [
                    'type' => 'string',
                    'enum' => collect($this->toolbox->spots())->flatMap(fn (array $spot) => collect($spot['activities'])->pluck('id'))->unique()->values()->all(),
                    'description' => 'The id of an activity that spot offers.',
                ],
                'pose' => $this->toolbox->poseParameter(),
            ],
            'required' => ['spot', 'activity'],
        ];
    }

    public function handle(array $arguments): array
    {
        $writtenSpot = (string) ($arguments['spot'] ?? '');
        $writtenActivity = (string) ($arguments['activity'] ?? '');

        $spot = $this->toolbox->findSpot($writtenSpot);
        if ($spot === null) {
            throw new \RuntimeException(sprintf('There is no spot called "%s" here. Use one of the spot ids from the use tool.', $writtenSpot));
        }

        $activity = collect($spot['activities'])->first(fn (array $candidate) => $this->toolbox->sameName($candidate['id'], $writtenActivity) || $this->toolbox->sameName($candidate['name'], $writtenActivity));
        if ($activity === null) {
            throw new \RuntimeException(sprintf(
                '"%s" cannot be done at %s. It offers: %s.',
                $writtenActivity,
                $spot['id'],
                collect($spot['activities'])->pluck('id')->implode(', '),
            ));
        }

        if (in_array($spot['id'], $this->toolbox->occupiedSpots, true)) {
            throw new \RuntimeException(sprintf('%s is taken by someone else right now. Choose a free spot.', $spot['id']));
        }

        $pose = $this->toolbox->poseForActivity($activity, trim((string) ($arguments['pose'] ?? '')));
        $this->toolbox->choose(['verb' => 'use', 'target' => $spot['id'], 'activity' => $activity['id'], 'pose' => $pose]);

        return ['status' => 'started', 'note' => "You are heading to the {$spot['objectName']} to {$activity['name']}."];
    }
}
