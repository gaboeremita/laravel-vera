<?php

namespace App\Actions\Quests;

use App\Contracts\QuestTrigger;
use App\Models\WorldResident;
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

    /**
     * @param  ?OfferMoment  $moment  the current turn with a giver; leaves about the moment only hold with one
     */
    public function holds(?array $condition, WorldSessionQuest $run, QuestSessionState $state, string $scope, string $path = '', ?OfferMoment $moment = null): bool
    {
        if ($condition === null || $condition === []) {
            return false;
        }

        $kind = array_key_first($condition);
        $value = $condition[$kind];

        return match ($kind) {
            'all' => collect($value)->every(fn ($child, $index) => $this->holds(is_array($child) ? $child : null, $run, $state, $scope, "{$path}.all.{$index}", $moment)),
            'any' => collect($value)->contains(fn ($child, $index) => $this->holds(is_array($child) ? $child : null, $run, $state, $scope, "{$path}.any.{$index}", $moment)),
            'not' => ! $this->holds(is_array($value) ? $value : null, $run, $state, $scope, "{$path}.not", $moment),
            'enterRegion', 'enterZone', 'talkTo', 'use', 'residentDid' => $run->hasSeen(self::seenKey($scope, $path, $condition)),
            'has' => $state->holds((int) ($value['item'] ?? 0), (int) ($value['atLeast'] ?? 1)),
            'credits' => $state->hasCredits((int) ($value['atLeast'] ?? 0)),
            'knows' => $state->knows((int) $value),
            'acknowledged' => $state->acknowledged((int) ($value['fact'] ?? 0), (int) ($value['resident'] ?? 0)),
            'flag' => is_string($value) ? $run->hasFlag($value) : $state->questEndedWithFlag((string) ($value['quest'] ?? ''), (string) ($value['name'] ?? '')),
            'question' => $run->questionMet((string) $value),
            'beat' => $run->hasFinished((string) $value),
            'feeling' => $this->withinBounds($state->feeling((int) ($value['resident'] ?? 0), (string) ($value['kind'] ?? '')), $value),
            'questState' => $state->questState((string) ($value['quest'] ?? '')) === ($value['state'] ?? null),
            'declinedTimes' => $state->declinedTimes((string) ($value['quest'] ?? '')) >= (int) ($value['atLeast'] ?? 1),
            'gaveTo' => $state->gaveTo((int) ($value['resident'] ?? 0), (int) ($value['item'] ?? 0)) >= (int) ($value['atLeast'] ?? 1),
            'spentWith' => $state->spentWith((int) ($value['resident'] ?? 0)) >= (int) ($value['atLeast'] ?? 1),
            'messagesWith', 'giverIn', 'othersInTheZone' => $moment !== null && $this->holdsNow($kind, $value, $moment),
            default => false,
        };
    }

    /**
     * @param  array{atLeast?: float|int, atMost?: float|int}  $bounds
     */
    private function withinBounds(float $feeling, array $bounds): bool
    {
        return (! isset($bounds['atLeast']) || $feeling >= $bounds['atLeast'])
            && (! isset($bounds['atMost']) || $feeling <= $bounds['atMost']);
    }

    /**
     * Leaves about the turn with the giver, read from the moment.
     */
    private function holdsNow(string $kind, mixed $value, OfferMoment $moment): bool
    {
        if ($kind === 'messagesWith') {
            $resident = WorldResident::find((int) ($value['resident'] ?? 0));

            return $resident !== null && $moment->messagesWith($resident) >= (int) ($value['atLeast'] ?? 1);
        }
        if ($kind === 'giverIn') {
            return $moment->region?->id === ($value['region'] ?? null)
                && collect($moment->giverZoneChain())->contains('id', $value['zone'] ?? null);
        }
        if ($moment->giverZoneChain() === []) {
            return false;
        }

        return ($value['nobody'] ?? false) === true
            ? $moment->residentsInGiverZone() === []
            : in_array((int) ($value['resident'] ?? 0), $moment->residentsInGiverZone(), true);
    }
}
