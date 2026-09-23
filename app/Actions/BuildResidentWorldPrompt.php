<?php

namespace App\Actions;

use App\Models\ResidentActivity;
use App\Models\World;
use App\Models\WorldResident;
use App\Models\WorldSession;

class BuildResidentWorldPrompt
{
    private const RECENT_ACTIVITY_LIMIT = 8;

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

    public function actionInstructions(): string
    {
        return implode("\n", [
            'Actions: to move in the world, add one action tag to your reply, using only the ids in brackets above.',
            '[action: go_to <place or thing id>] walks you there.',
            '[action: follow] follows the user until you stop.',
            '[action: stop] stops what you are doing.',
            'Only one action per reply. After it finishes, your recent activity tells you how it went.',
        ]);
    }

    public function recentActivity(World $world, WorldSession $session, WorldResident $resident): ?string
    {
        $activities = ResidentActivity::where('world_session_id', $session->id)
            ->where('world_resident_id', $resident->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get();

        if ($activities->isEmpty()) {
            return null;
        }

        $zoneNames = collect($world->layout['zones'] ?? [])->pluck('name', 'id');
        $lines = $activities->map(function (ResidentActivity $activity) use ($zoneNames) {
            $line = '- '.$this->describeActivity($activity);
            if ($activity->zone_id !== null && $zoneNames->has($activity->zone_id)) {
                $line .= ', from '.$zoneNames->get($activity->zone_id);
            }
            if ($activity->reason === 'direct control') {
                $line .= ', because the user used a direct control';
            } elseif ($activity->reason !== null) {
                $line .= ', because '.$activity->reason;
            }

            $outcome = $activity->outcome ?? 'in progress';
            if ($activity->outcome_reason !== null) {
                $outcome .= " ({$activity->outcome_reason})";
            }
            $minutes = (int) floor($activity->created_at->diffInMinutes(now(), true));

            return "{$line}: {$outcome}, ".($minutes < 1 ? 'just now' : "{$minutes} min ago");
        });

        return "Your recent activity, newest first:\n".$lines->implode("\n");
    }

    private function describeActivity(ResidentActivity $activity): string
    {
        return match ($activity->verb) {
            'go_to' => "walked toward {$activity->target}",
            'follow' => 'followed the user',
            'stop' => 'stopped',
            'use' => "{$activity->activity} at {$activity->target}",
            'zone' => (string) $activity->activity,
            'stay' => 'stayed put',
            'pose' => "posed: {$activity->target}",
            'invalid' => 'tried an action that does not exist',
            default => $activity->verb,
        };
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
