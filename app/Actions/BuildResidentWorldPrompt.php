<?php

namespace App\Actions;

use App\Enums\Posture;
use App\Models\Assistant;
use App\Models\ResidentActivity;
use App\Models\World;
use App\Models\WorldResident;
use App\Models\WorldSession;

class BuildResidentWorldPrompt
{
    private const RECENT_ACTIVITY_LIMIT = 8;

    /**
     * Poses the world plays for her (walking, greeting someone who starts a conversation), which are never hers to choose.
     */
    private const WORLD_MOTION_POSE_NAMES = ['walk', 'walking', 'walk-cycle', 'walk_cycle', 'walk cycle', 'walk-start', 'walk_start', 'walk start', 'walk-stop', 'walk_stop', 'walk stop', 'greeting', 'greet'];

    /**
     * @param  array{floor: ?array, zone: ?array, zoneChain: array<int, array>, distanceToUser?: ?float}  $resident
     * @param  ?array{floor: ?array, zone: ?array, zoneChain: array<int, array>}  $user
     * @return array<string, string|array<int, string>>
     */
    public function worldState(World $world, array $resident, ?array $user): array
    {
        $layout = $world->layout ?? [];
        $state = ['you are in' => $this->placePhrase($resident)];

        if ($resident['zone'] !== null) {
            $state['here'] = $resident['zone']['description'];

            if ($resident['zone']['activities'] !== []) {
                $state['things to do here'] = collect($resident['zone']['activities'])
                    ->map(fn (array $activity) => "{$activity['name']} [{$activity['id']}]")->all();
            }

            $objects = collect($layout['objects'] ?? [])->where('zoneId', $resident['zone']['id']);
            if ($objects->isNotEmpty()) {
                $state['things here'] = $objects->map(fn (array $object) => $this->objectPhrase($object))->values()->all();
            }
        }

        if ($user !== null) {
            $state['the user is'] = sprintf(
                '%sin %s, %s',
                $this->relativeFloor($resident['floor'], $user['floor']),
                $this->placePhrase($user),
                $this->distancePhrase($resident['distanceToUser'] ?? null),
            );
        }

        $state['available places'] = collect($layout['zones'] ?? [])->map(fn (array $zone) => $this->zoneName($layout, $zone))->all();

        return $state;
    }

    public function worldAwareness(): string
    {
        return "World awareness:\nRemember that your tools are yours to use whenever you feel like it, on your own initiative, whether or not the user asks: what_is_in shows what a place holds and what you can do there, where_can_i finds where you could do something, describe tells you more about a place or thing, go_to, follow and stop move you, use sits, lies or reclines you on a spot for an activity, zone does an activity of the place you are in, and plan does something that takes several steps, in order. Reach for them whenever a thought, a mood, a craving or the conversation brings the space to mind, the way anyone glances around a room.\nThink in steps: getting a drink is going to the bar, mixing it at the back bar, then sitting on a stool to drink it, so call plan with those steps. Anything you want to do works even with no marked spot or pose for it, such as singing at the microphone or making tea at the counter: go there, then add a do step describing it, and your narration carries it.\nPostures are exact: sitting is upright on a seat, reclining is leaning far back on a lounger, a bed or in a bath, and lying is flat on your back or side on a bed.";
    }

    /**
     * What she could do right now, for an idle decision: her poses by posture,
     * the activities of the place she is in, and its spots marked free or taken.
     *
     * @param  array{floor: ?array, zone: ?array, zoneChain: array<int, array>}  $location
     * @param  array<int, string>  $occupiedSpots
     * @return array<string, string|array<int, string>>
     */
    public function availableActivities(World $world, Assistant $assistant, array $location, array $occupiedSpots, Posture $posture): array
    {
        $available = ['you are' => $posture->value];

        $poses = $assistant->poses()->orderBy('id')->get(['name', 'posture'])
            ->reject(fn ($pose) => $pose->name === 'default' || in_array(mb_strtolower(trim($pose->name)), self::WORLD_MOTION_POSE_NAMES, true))
            ->groupBy('name')
            ->map(fn ($versions, string $name) => sprintf('%s (%s)', $name, $versions->map(fn ($pose) => $pose->posture->value)->implode(', ')));
        if ($poses->isNotEmpty()) {
            $available['poses'] = $poses->values()->all();
        }

        $zoneActivities = collect($location['zoneChain'])->flatMap(fn (array $zone) => collect($zone['activities'])->map(fn (array $activity) => "{$activity['name']} [{$activity['id']}] in {$zone['name']}"));
        if ($zoneActivities->isNotEmpty()) {
            $available['things to do in this place'] = $zoneActivities->values()->all();
        }

        $zoneIds = collect($location['zoneChain'])->pluck('id');
        $spots = collect($world->layout['objects'] ?? [])
            ->filter(fn (array $object) => $zoneIds->contains($object['zoneId']))
            ->flatMap(fn (array $object) => collect($object['spots'])->map(fn (array $spot) => sprintf(
                '%s [%s] at the %s: %s (%s)',
                $spot['id'],
                $object['id'],
                $object['name'],
                collect($spot['activities'])->map(fn (array $activity) => "{$activity['name']} [{$activity['id']}]")->implode(', '),
                in_array($spot['id'], $occupiedSpots, true) ? 'taken' : 'free',
            )));
        if ($spots->isNotEmpty()) {
            $available['spots in this place'] = $spots->values()->all();
        }

        $available['elsewhere'] = 'where_can_i and what_is_in tell you what other places offer.';

        return $available;
    }

    public function idleInstruction(): string
    {
        return "Your next step:\nThe user is somewhere in the world and leaves you to yourself right now. Decide what you do next, the way you would on your own: call one action tool (go_to, use, zone, follow or stop), call plan for something that takes several steps, use one pose tag, or stay where you are. Choose what fits your mood, your personality and what you did recently, and vary your activities; when you repeat your previous one, give a reason for doing it again. Reply with exactly one short line of at most 20 words: a brief reason as a thought in parentheses, followed by what you do as a brief action in asterisks, for example (I want to forget about today) *walks to the bar for a drink*.";
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
            'plan' => "planned to {$activity->target}",
            'do' => (string) $activity->target,
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
}
