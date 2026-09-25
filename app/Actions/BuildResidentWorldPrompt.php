<?php

namespace App\Actions;

use App\Enums\ConversationStatus;
use App\Enums\Posture;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Pose;
use App\Models\ResidentActivity;
use App\Models\World;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Support\Str;

class BuildResidentWorldPrompt
{
    private const RECENT_ACTIVITY_LIMIT = 8;

    private const RECENT_CONVERSATION_LIMIT = 6;

    private const RECENT_MESSAGE_LENGTH = 300;

    private const OTHER_CONVERSATION_LIMIT = 3;

    private const OTHER_CONVERSATION_LINES = 6;

    private const RESTING_POSTURES = ['sitting', 'lying', 'reclining'];

    /**
     * @param  array{floor: ?array, zone: ?array, zoneChain: array<int, array>, distanceToUser?: ?float}  $resident
     * @param  ?array{floor: ?array, zone: ?array, zoneChain: array<int, array>}  $user
     * @param  ?array{posture: string, object: ?array, activity: ?array}  $userActivity
     * @param  array<int, string>  $stacking  who lies on top of her and whom she lies on top of
     * @param  ?string  $userTalkingWith  the name of whoever the user is busy talking with
     * @param  ?array{posture: string, object: ?array, activity: ?array}  $residentActivity  her own posture and what she is on or doing
     * @return array<string, string|array<int, string>>
     */
    public function worldState(World $world, array $resident, ?array $user, ?array $userActivity = null, array $stacking = [], ?string $userTalkingWith = null, ?array $residentActivity = null): array
    {
        $layout = $world->layout ?? [];
        $state = ['you are in' => $this->placePhrase($resident).$this->activityPhrase($residentActivity)];

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
                '%sin %s, %s%s%s',
                $this->relativeFloor($resident['floor'], $user['floor']),
                $this->placePhrase($user),
                $this->distancePhrase($resident['distanceToUser'] ?? null),
                $this->activityPhrase($userActivity),
                $userTalkingWith !== null ? ", busy talking with {$userTalkingWith}" : '',
            );
        }

        if ($stacking !== []) {
            $state['sharing your spot'] = $stacking;
        }

        $state['available places'] = collect($layout['zones'] ?? [])->map(fn (array $zone) => $this->zoneName($layout, $zone))->all();

        return $state;
    }

    public function worldAwareness(): string
    {
        return "World awareness:\nYour body in this world moves only through your tools. Whenever your reply has you go somewhere, approach or leave someone, sit, lie down, recline, follow someone or stop, call the matching tool in that same reply, and let your narration describe what the tool does. Reach for your tools on your own initiative, whether or not the user asks: what_is_in shows what a place holds and what you can do there, where_can_i finds where you could do something, describe tells you more about a place or thing, go_to, follow and stop move you, use sits, lies or reclines you on a spot for an activity, zone does an activity of the place you are in, and plan does something that takes several steps, in order. go_to with target 'user' brings you to the user; in the water it swims you to the side of the pool nearest them, where you rest at the edge, and swim_to_edge takes you to the nearest side to rest there on your own. When nothing in particular calls you, wander lets you roam and explore for a while, around a place or around where you are; in the water it swims you around the pool. Reach for them whenever a thought, a mood, a craving or the conversation brings the space to mind, the way anyone glances around a room.\nPeople name things loosely; a couch can mean a sofa or the armchairs. Match what they mean to the closest fitting thing, and prefer what is near you.\nThink in steps: getting a drink is going to the bar, mixing it at the back bar, then sitting on a stool to drink it, so call plan with those steps. Anything you want to do works even with no marked spot or pose for it, such as singing at the microphone or making tea at the counter: go there, then add a do step describing it, and your narration carries it.\nPostures are exact: sitting is upright on a seat, reclining is leaning far back on a lounger, a bed or in a bath, and lying is flat on your back or side on a bed.\nA pose tag sets your gesture or expression where you are right now; moving and changing posture come from your tools.";
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

        $poses = $assistant->poses()->orderBy('id')->get(['name', 'posture', 'restricted'])
            ->filter(fn (Pose $pose) => $pose->isChosen());
        $describePoses = fn ($group) => $group->groupBy('name')
            ->map(fn ($versions, string $name) => sprintf('%s (%s)', $name, $versions->map(fn ($pose) => $pose->posture->value)->implode(', ')))
            ->values()->all();
        if ($poses->isNotEmpty()) {
            [$restricted, $regular] = $poses->partition(fn ($pose) => $pose->restricted);
            $available['poses'] = ['regular' => $describePoses($regular), 'restricted' => $describePoses($restricted)];
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
        return "Your next step:\nSome time has passed since your last step. Read the moment before you choose: where you are, who is with you and what you are doing together, what you and the user last said to each other, and how that exchange felt. What this place offers is your natural first choice: its spots, its activities and the people in it. The longer you have been in one place, the more a change of scene appeals, so after a good while there you get restless and head somewhere else, with a reason. When you are sitting, reclining or lying, you settle in and stay a while: thinking, remembering, small gestures and talking are the natural things to do there, and you get up once you have been there a good while or something calls you away. Life is more than actions: sometimes you simply think about something, or a memory comes back to you, most of all while you rest and now and then while you are up and about. Pick what this moment calls for, the way you would in your own life. Let the mood of your last exchange with the user carry into what you do. When someone is with you and you go elsewhere, have a reason for leaving and tell or show it to them. Act by calling one action tool (go_to, use, zone, wander, swim_to_edge, follow, stop, talk_to, think or remember), call plan for something that takes several steps, use one pose tag, or stay as you are. Reply with exactly one short line of at most 20 words: a brief reason as a thought in parentheses, followed by what you do as a brief action in asterisks, for example (I want to forget about today) *walks to the bar for a drink*.";
    }

    /**
     * The other residents of the world, where they are from her, and the
     * conversation she has with each of them, where it stopped.
     *
     * @param  ?array{user?: array{x: float, y: float, z: float}, residents?: array<int|string, array{x: float, y: float, z: float}>}  $positions
     * @param  array<int, ?int>  $busyWith  for each resident who is busy, the resident she is talking with or on her way to
     * @return array<int, string>
     */
    public function companions(World $world, WorldResident $resident, ?array $positions, array $busyWith = []): array
    {
        $resolveWorldState = new ResolveWorldState;
        $own = $positions['residents'][$resident->id] ?? null;
        $residents = $world->residents()->with('assistant')->get();
        $names = $residents->mapWithKeys(fn (WorldResident $candidate) => [$candidate->id => $candidate->assistant->name]);

        return $residents->reject(fn (WorldResident $other) => $other->id === $resident->id)->values()
            ->map(function (WorldResident $other) use ($world, $resident, $positions, $own, $resolveWorldState, $busyWith, $names): string {
                $point = $positions['residents'][$other->id] ?? null;
                $where = $point === null
                    ? 'somewhere in the world'
                    : sprintf('in %s, %s', $this->placePhrase($resolveWorldState->locate($world->layout ?? [], $point)), $this->distancePhrase($own === null ? null : $resolveWorldState->distance($own, $point)));
                $paused = Conversation::between($resident->assistant, $other->assistant)
                    ->where('status', ConversationStatus::Paused)
                    ->latest('updated_at')
                    ->first();
                $lastLine = $paused?->messages()->latest('id')->value('content');
                $left = $lastLine !== null
                    ? sprintf('; your conversation with them stopped at "%s"', Str::limit((string) $lastLine, self::RECENT_MESSAGE_LENGTH, ''))
                    : '';

                $busy = array_key_exists($other->id, $busyWith)
                    ? ($busyWith[$other->id] === $resident->id ? ', busy with you' : ', busy talking with '.($names[$busyWith[$other->id]] ?? 'someone'))
                    : '';

                return "{$other->assistant->name}: {$where}{$busy}{$left}";
            })
            ->all();
    }

    /**
     * Her latest conversations with other residents, so she remembers them
     * when she talks to the user.
     */
    public function conversationsWithOthers(Assistant $assistant, WorldSession $session): ?string
    {
        $conversations = Conversation::involving($assistant)
            ->where('world_session_id', $session->id)
            ->where('owner_type', $assistant->getMorphClass())
            ->where('counterpart_type', $assistant->getMorphClass())
            ->latest('updated_at')
            ->limit(self::OTHER_CONVERSATION_LIMIT)
            ->with(['owner', 'counterpart'])
            ->get();

        if ($conversations->isEmpty()) {
            return null;
        }

        return $conversations->map(function (Conversation $conversation) use ($assistant): string {
            $other = $conversation->owner->is($assistant) ? $conversation->counterpart : $conversation->owner;
            $lines = $conversation->messages()->latest('id')->limit(self::OTHER_CONVERSATION_LINES)->get(['speaker_type', 'speaker_id', 'content'])->reverse()
                ->map(fn ($message) => ($message->speaker_id === $assistant->id ? 'you: ' : "{$other->name}: ").Str::limit((string) $message->content, self::RECENT_MESSAGE_LENGTH, ''))
                ->implode("\n");

            return "With {$other->name}:\n{$lines}";
        })->implode("\n\n");
    }

    /**
     * What a resident is asked for on her turn in a conversation with
     * another resident.
     */
    public function conversationTurnInstruction(string $otherName): string
    {
        return "Talking with {$otherName}:\nYou are talking with {$otherName} in person, right where you both are. Their lines come to you as messages starting with their name. Reply with what you say next, in your own voice: one to three sentences, with any action in asterisks. Follow the thread of the conversation; when it has run its course for now, or you want to pick it up another time, say your goodbye and call stop_conversation.";
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

    /**
     * Her session conversation's last few messages, so her decision knows what was said and what she saw the user do.
     */
    public function recentConversation(Conversation $conversation): ?string
    {
        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit(self::RECENT_CONVERSATION_LIMIT)
            ->get(['role', 'content'])
            ->reverse();

        if ($messages->isEmpty()) {
            return null;
        }

        return "Recent conversation, oldest first:\n".$messages
            ->map(fn ($message) => ($message->role === 'assistant' ? 'you: ' : 'the user: ').Str::limit((string) $message->content, self::RECENT_MESSAGE_LENGTH, ''))
            ->implode("\n");
    }

    /**
     * Someone's posture, the thing they are on or at, the activity they are doing there and the pose they hold.
     *
     * @param  ?array{posture: string, object: ?array, activity: ?array, pose?: ?string}  $bodyActivity
     */
    private function activityPhrase(?array $bodyActivity): string
    {
        if ($bodyActivity === null) {
            return '';
        }

        ['posture' => $posture, 'object' => $object, 'activity' => $activity] = $bodyActivity;
        $pose = $bodyActivity['pose'] ?? null;
        $preposition = in_array($posture, self::RESTING_POSTURES, true) ? 'on' : 'at';
        $position = trim(($posture === 'standing' ? '' : $posture).($object !== null ? " {$preposition} the {$object['name']}" : ''));

        return collect([
            $position,
            $activity !== null ? "doing \"{$activity['name']}\"" : '',
            $pose !== null ? "holding the pose \"{$pose}\"" : '',
        ])->filter()->map(fn (string $part) => ", {$part}")->implode('');
    }

    /**
     * How long she has been in the place she is in and at what she is doing,
     * why she is doing it and how she put it when she started, from the
     * activity behind her current state.
     *
     * @param  ?array<string, mixed>  $zone  the place she is in
     * @param  ?array{posture: string, object: ?array, activity: ?array, pose?: ?string}  $residentActivity
     */
    public function currentActivity(WorldSession $session, WorldResident $resident, ?array $zone, ?array $residentActivity): ?string
    {
        $activities = ResidentActivity::where('world_session_id', $session->id)->where('world_resident_id', $resident->id);

        $arrivedAt = $zone === null ? null : ((clone $activities)->where('zone_id', '!=', $zone['id'])->latest('id')->value('created_at') ?? $session->created_at);
        $activityId = $residentActivity['activity']['id'] ?? null;
        $current = $activityId === null ? null : (clone $activities)->where('activity', $activityId)->latest('id')->first();
        $narrated = $current === null ? null : (clone $activities)->where('id', '<=', $current->id)->whereNotNull('narration')->latest('id')->first();

        $lines = collect([
            $arrivedAt !== null ? "You have been in {$zone['name']} for {$this->durationSince($arrivedAt)}." : null,
            $current !== null ? "You have been at it for {$this->durationSince($current->created_at)}." : null,
            $current?->reason !== null ? "Why: {$current->reason}" : null,
            $narrated !== null ? "In your words when you started: {$narrated->narration}" : null,
        ])->filter();

        return $lines->isEmpty() ? null : "What you are doing now:\n".$lines->implode("\n");
    }

    private function durationSince(\DateTimeInterface $moment): string
    {
        $minutes = (int) floor(now()->diffInMinutes($moment, true));

        return $minutes < 1 ? 'less than a minute' : ($minutes === 1 ? 'about a minute' : "about {$minutes} minutes");
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
            'think' => "thought about {$activity->target}",
            'remember' => "remembered something from {$activity->target}",
            'pose' => "posed: {$activity->target}",
            'plan' => "planned to {$activity->target}",
            'do' => (string) $activity->target,
            'swim_to_edge' => 'swam to the side of the pool',
            'wander' => $activity->target !== null ? "wandered around {$activity->target}" : 'wandered around',
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
