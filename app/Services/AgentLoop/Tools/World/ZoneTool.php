<?php

namespace App\Services\AgentLoop\Tools\World;

class ZoneTool extends WorldTool
{
    public function name(): string
    {
        return 'zone';
    }

    public function description(): string
    {
        return 'Does an activity the place you are in offers, right where you stand, such as swimming in the pool. To do an activity somewhere else, go_to that place first.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'activity' => ['type' => 'string', 'enum' => $this->toolbox->zoneActivityIds(), 'description' => 'The id of an activity the place you are in offers.'],
            ],
            'required' => ['activity'],
        ];
    }

    public function handle(array $arguments): array
    {
        $written = (string) ($arguments['activity'] ?? '');
        $zones = $this->toolbox->residentZoneChain !== [] ? $this->toolbox->residentZoneChain : $this->toolbox->zones();

        $activity = collect($zones)->flatMap(fn (array $zone) => $zone['activities'])
            ->first(fn (array $candidate) => $this->toolbox->sameName($candidate['id'], $written) || $this->toolbox->sameName($candidate['name'], $written));

        if ($activity === null) {
            $offered = collect($zones)->flatMap(fn (array $zone) => collect($zone['activities'])->pluck('id'));

            throw new \RuntimeException(sprintf(
                '"%s" is not something you can do where you are. %s',
                $written,
                $offered->isEmpty() ? 'This place offers no activities; where_can_i finds places that do.' : 'Here you can: '.$offered->implode(', ').'.',
            ));
        }

        $this->toolbox->choose(['verb' => 'zone', 'target' => null, 'activity' => $activity['id']]);

        return ['status' => 'started', 'note' => "You start to {$activity['name']}."];
    }
}
