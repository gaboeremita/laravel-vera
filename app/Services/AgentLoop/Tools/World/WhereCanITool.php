<?php

namespace App\Services\AgentLoop\Tools\World;

class WhereCanITool extends WorldTool
{
    public function name(): string
    {
        return 'where_can_i';
    }

    public function description(): string
    {
        return 'Finds every place in this world where you can do something, such as swim, sit or play music. Returns each place with the thing, spot and activity id to use there.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'activity' => ['type' => 'string', 'description' => 'A few words describing what you want to do.'],
            ],
            'required' => ['activity'],
        ];
    }

    public function handle(array $arguments): array
    {
        $words = $this->words((string) ($arguments['activity'] ?? ''));
        if ($words === []) {
            throw new \RuntimeException('where_can_i needs an activity to look for.');
        }

        $matches = fn (array $activity) => array_intersect($words, [...$this->words($activity['id']), ...$this->words($activity['name'])]) !== [];
        $results = [];

        foreach ($this->toolbox->zones() as $zone) {
            foreach (array_filter($zone['activities'], $matches) as $activity) {
                $results[] = [
                    'place' => $zone['name'],
                    'placeId' => $zone['id'],
                    'floor' => $this->toolbox->floorName($zone['floorId']),
                    'activity' => $activity['name'],
                    'activityId' => $activity['id'],
                ];
            }
        }

        foreach ($this->toolbox->objects() as $object) {
            $place = $this->toolbox->placeReference($object['zoneId']);
            foreach ($object['spots'] as $spot) {
                foreach (array_filter($spot['activities'], $matches) as $activity) {
                    $results[] = [
                        'place' => $place['name'] ?? null,
                        'placeId' => $place['id'] ?? null,
                        'thing' => $object['name'],
                        'thingId' => $object['id'],
                        'spotId' => $spot['id'],
                        'activity' => $activity['name'],
                        'activityId' => $activity['id'],
                        'posture' => $activity['posture'],
                    ];
                }
            }
        }

        return ['matches' => $results];
    }

    /**
     * @return array<int, string>
     */
    private function words(string $value): array
    {
        return array_values(array_filter(explode('-', $this->toolbox->normalize($value))));
    }
}
