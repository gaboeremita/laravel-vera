<?php

namespace App\Actions\Quests;

use App\Contracts\QuestTrigger;
use App\Models\WorldSessionQuest;

/**
 * Evaluates a quest's condition trees.
 *
 * Leaves about something that happens (entering, talking, using) latch: once
 * a trigger matches one while its condition is watched, the run remembers it
 * under a key in `state.seen`, and it holds from then on. Leaves about
 * something held are read from the session as it is.
 */
class QuestConditions
{
    public const EVENT_LEAVES = ['enterRegion', 'enterZone', 'talkTo', 'use', 'residentDid'];

    /**
     * The key a latched leaf is remembered under: the scope it is watched in
     * (`beat:<id>`, `start`, `complete` or `fail`), then a hash of where it
     * sits in the tree and what it says.
     */
    public static function seenKey(string $scope, string $path, array $leaf): string
    {
        return $scope.'|'.substr(md5($path.json_encode($leaf)), 0, 16);
    }

    public static function beatOfSeenKey(string $key): ?string
    {
        return str_starts_with($key, 'beat:') ? substr($key, 5, strpos($key, '|') - 5) : null;
    }

    /**
     * The kinds of leaves a condition contains.
     *
     * @return array<int, string>
     */
    public function leavesOf(?array $condition): array
    {
        if ($condition === null || $condition === []) {
            return [];
        }

        $kind = array_key_first($condition);

        return match ($kind) {
            'all', 'any' => array_values(array_unique(array_merge([], ...array_map(fn ($child) => $this->leavesOf(is_array($child) ? $child : null), $condition[$kind] ?? [])))),
            'not' => $this->leavesOf(is_array($condition['not']) ? $condition['not'] : null),
            default => [$kind],
        };
    }

    /**
     * Records every event leaf the trigger matches.
     *
     * @return bool whether anything new was latched
     */
    public function latch(?array $condition, WorldSessionQuest $run, QuestTrigger $trigger, string $scope, string $path = ''): bool
    {
        if ($condition === null || $condition === []) {
            return false;
        }

        $kind = array_key_first($condition);
        $value = $condition[$kind];

        if (in_array($kind, ['all', 'any'], true)) {
            $latched = false;
            foreach ($value as $index => $child) {
                $latched = $this->latch(is_array($child) ? $child : null, $run, $trigger, $scope, "{$path}.{$kind}.{$index}") || $latched;
            }

            return $latched;
        }
        if ($kind === 'not') {
            return $this->latch(is_array($value) ? $value : null, $run, $trigger, $scope, "{$path}.not");
        }

        $key = self::seenKey($scope, $path, $condition);
        if (! in_array($kind, self::EVENT_LEAVES, true) || $run->hasSeen($key) || ! $trigger->matches($kind, $value)) {
            return false;
        }

        $run->mergeState(['seen' => [...($run->state['seen'] ?? []), $key]]);

        return true;
    }

    public function holds(?array $condition, WorldSessionQuest $run, QuestSessionState $state, string $scope, string $path = ''): bool
    {
        if ($condition === null || $condition === []) {
            return false;
        }

        $kind = array_key_first($condition);
        $value = $condition[$kind];

        return match ($kind) {
            'all' => collect($value)->every(fn ($child, $index) => $this->holds(is_array($child) ? $child : null, $run, $state, $scope, "{$path}.all.{$index}")),
            'any' => collect($value)->contains(fn ($child, $index) => $this->holds(is_array($child) ? $child : null, $run, $state, $scope, "{$path}.any.{$index}")),
            'not' => ! $this->holds(is_array($value) ? $value : null, $run, $state, $scope, "{$path}.not"),
            'enterRegion', 'enterZone', 'talkTo', 'use', 'residentDid' => $run->hasSeen(self::seenKey($scope, $path, $condition)),
            'has' => $state->holds((int) ($value['item'] ?? 0), (int) ($value['atLeast'] ?? 1)),
            'credits' => $state->hasCredits((int) ($value['atLeast'] ?? 0)),
            'knows' => $state->knows((int) $value),
            'acknowledged' => $state->acknowledged((int) ($value['fact'] ?? 0), (int) ($value['resident'] ?? 0)),
            'flag' => is_string($value) ? $run->hasFlag($value) : $state->questEndedWithFlag((string) ($value['quest'] ?? ''), (string) ($value['name'] ?? '')),
            'question' => $run->questionMet((string) $value),
            'beat' => $run->hasFinished((string) $value),
            default => false,
        };
    }
}
