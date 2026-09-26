<?php

namespace App\Services\AgentLoop\Tools\World;

class DescribeTool extends WorldTool
{
    public function name(): string
    {
        return 'describe';
    }

    public function description(): string
    {
        return 'Describes a place or thing in this world.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'enum' => $this->toolbox->targetIds(), 'description' => 'The id of the place, thing or spot.'],
            ],
            'required' => ['id'],
        ];
    }

    public function handle(array $arguments): array
    {
        $written = (string) ($arguments['id'] ?? '');

        $zone = $this->toolbox->findZone($written);
        if ($zone !== null) {
            return [
                'kind' => 'place',
                'name' => $zone['name'],
                'id' => $zone['id'],
                'description' => $zone['description'],
                'floor' => $this->toolbox->floorName($zone['floorId']),
                'insideOf' => $this->toolbox->placeReference($zone['parentId']),
                'access' => $this->toolbox->accessNote($zone),
            ];
        }

        $object = $this->toolbox->findTarget($written);
        if ($object !== null) {
            return [
                'kind' => 'thing',
                'name' => $object['name'],
                'id' => $object['id'],
                'description' => $object['description'],
                'place' => $this->toolbox->placeReference($object['zoneId']),
            ];
        }

        throw new \RuntimeException(sprintf('There is no place, thing or spot called "%s" here. Use one of the ids from the describe tool.', $written));
    }
}
