<?php

namespace App\Services\AgentLoop\Tools\World;

class WhatIsInTool extends WorldTool
{
    public function name(): string
    {
        return 'what_is_in';
    }

    public function description(): string
    {
        return 'Tells you what a place in this world contains: its description, what you can do there, the places inside it, and its things with their spots and activities.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'place' => ['type' => 'string', 'enum' => collect($this->toolbox->zones())->pluck('id')->all(), 'description' => 'The id of the place.'],
            ],
            'required' => ['place'],
        ];
    }

    public function handle(array $arguments): array
    {
        $written = (string) ($arguments['place'] ?? '');
        $zone = $this->toolbox->findZone($written);
        if ($zone === null) {
            throw new \RuntimeException(sprintf('There is no place called "%s" here. Use one of the place ids from the what_is_in tool.', $written));
        }

        return [
            'name' => $zone['name'],
            'id' => $zone['id'],
            'description' => $zone['description'],
            'floor' => $this->toolbox->floorName($zone['floorId']),
            'insideOf' => $this->toolbox->placeReference($zone['parentId']),
            'activities' => collect($zone['activities'])->map(fn (array $activity) => ['name' => $activity['name'], 'id' => $activity['id']])->all(),
            'placesInside' => collect($this->toolbox->zones())->where('parentId', $zone['id'])->map(fn (array $child) => ['name' => $child['name'], 'id' => $child['id']])->values()->all(),
            'things' => collect($this->toolbox->objects())->where('zoneId', $zone['id'])->map(fn (array $object) => [
                'name' => $object['name'],
                'id' => $object['id'],
                'description' => $object['description'],
                'spots' => collect($object['spots'])->map(fn (array $spot) => [
                    'id' => $spot['id'],
                    'activities' => collect($spot['activities'])->map(fn (array $activity) => [
                        'name' => $activity['name'],
                        'id' => $activity['id'],
                        'posture' => $activity['posture'],
                    ])->all(),
                ])->all(),
            ])->values()->all(),
        ];
    }
}
