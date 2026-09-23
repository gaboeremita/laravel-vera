<?php

namespace App\Actions;

use App\Models\World;

class BuildResidentWorldPrompt
{
    /**
     * @param  array{floor: ?array, zone: ?array, zoneChain: array<int, array>, distanceToUser?: ?float}  $resident
     * @param  ?array{floor: ?array, zone: ?array, zoneChain: array<int, array>}  $user
     */
    public function worldState(World $world, array $resident, ?array $user): string
    {
        $layout = $world->layout ?? [];
        $lines = ['World state:', 'You are in: '.$this->placePhrase($resident).'.'];

        if ($resident['zone'] !== null) {
            $lines[] = $resident['zone']['description'];

            if ($resident['zone']['activities'] !== []) {
                $lines[] = 'Things to do here: '.$this->activityList($resident['zone']['activities']);
            }

            $objects = collect($layout['objects'] ?? [])->where('zoneId', $resident['zone']['id']);
            if ($objects->isNotEmpty()) {
                $lines[] = 'Things here: '.$objects->map(fn (array $object) => $this->objectPhrase($object))->implode('; ');
            }
        }

        if ($user !== null) {
            $lines[] = sprintf(
                'The user is %sin: %s, %s.',
                $this->relativeFloor($resident['floor'], $user['floor']),
                $this->placePhrase($user),
                $this->distancePhrase($resident['distanceToUser'] ?? null),
            );
        }

        $otherPlaces = collect($layout['zones'] ?? [])
            ->reject(fn (array $zone) => $zone['id'] === ($resident['zone']['id'] ?? null))
            ->map(fn (array $zone) => $this->zoneName($layout, $zone));
        if ($otherPlaces->isNotEmpty()) {
            $lines[] = 'Other places: '.$otherPlaces->implode('; ');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{floor: ?array, zone: ?array, zoneChain: array<int, array>}  $location
     */
    private function placePhrase(array $location): string
    {
        $place = $location['zoneChain'] === []
            ? 'no marked place'
            : collect($location['zoneChain'])->reverse()->pluck('name')->implode(', inside ');

        return $location['floor'] !== null ? "{$place}, on the {$location['floor']['name']}" : $place;
    }

    private function relativeFloor(?array $residentFloor, ?array $userFloor): string
    {
        if ($residentFloor === null || $userFloor === null || $residentFloor['id'] === $userFloor['id']) {
            return '';
        }

        return $userFloor['minY'] > $residentFloor['minY'] ? 'upstairs, ' : 'downstairs, ';
    }

    private function distancePhrase(?float $distance): string
    {
        if ($distance === null) {
            return 'at an unknown distance';
        }

        return $distance < 1 ? 'less than 1 m away from you' : sprintf('about %d m away from you', round($distance));
    }

    private function zoneName(array $layout, array $zone): string
    {
        $floor = collect($layout['floors'] ?? [])->firstWhere('id', $zone['floorId']);

        return $floor !== null ? "{$zone['name']} [{$zone['id']}] ({$floor['name']})" : "{$zone['name']} [{$zone['id']}]";
    }

    private function objectPhrase(array $object): string
    {
        $spots = collect($object['spots'])->map(fn (array $spot) => sprintf(
            '%s (%s)',
            $spot['id'],
            collect($spot['activities'])->map(fn (array $activity) => $activity['posture'] !== null
                ? "{$activity['name']} [{$activity['id']}], {$activity['posture']}"
                : "{$activity['name']} [{$activity['id']}]")->implode('; '),
        ));

        $phrase = "{$object['name']} [{$object['id']}]: {$object['description']}";

        return $spots->isEmpty() ? $phrase : $phrase.' Spots: '.$spots->implode(', ');
    }

    private function activityList(array $activities): string
    {
        return collect($activities)->map(fn (array $activity) => "{$activity['name']} [{$activity['id']}]")->implode('; ');
    }
}
