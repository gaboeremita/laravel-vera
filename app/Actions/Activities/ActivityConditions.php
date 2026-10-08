<?php

namespace App\Actions\Activities;

use App\Actions\Quests\QuestSessionState;
use App\Models\Item;

/**
 * Evaluates a response's condition tree: the quest leaves about what the
 * session holds, plus the object's own state. A narrator leaf is judged by
 * the one narrator call of the use, so here it counts as met.
 */
class ActivityConditions
{
    public const LEAVES = ['has', 'credits', 'knows', 'acknowledged', 'flag', 'sentiment', 'questState', 'declinedTimes', 'gaveTo', 'spentWith', 'objectState', 'narrator'];

    /**
     * An empty condition always holds.
     */
    public function holds(?array $condition, ActivityUse $use, QuestSessionState $state): bool
    {
        if ($condition === null || $condition === []) {
            return true;
        }

        $kind = array_key_first($condition);
        $value = $condition[$kind];

        return match ($kind) {
            'all' => collect($value)->every(fn ($child) => $this->holds(is_array($child) ? $child : null, $use, $state)),
            'any' => collect($value)->contains(fn ($child) => $this->holds(is_array($child) ? $child : null, $use, $state)),
            'not' => ! $this->holds(is_array($value) ? $value : null, $use, $state),
            'has' => $state->holds((int) ($value['item'] ?? 0), (int) ($value['atLeast'] ?? 1)),
            'credits' => $state->hasCredits((int) ($value['atLeast'] ?? 0)),
            'knows' => $state->knows((int) $value),
            'acknowledged' => $state->acknowledged((int) ($value['fact'] ?? 0), (int) ($value['resident'] ?? 0)),
            'flag' => is_array($value) && $state->questEndedWithFlag((string) ($value['quest'] ?? ''), (string) ($value['name'] ?? '')),
            'sentiment' => $this->withinBounds($state->sentiment((int) ($value['resident'] ?? 0), (string) ($value['kind'] ?? '')), $value),
            'questState' => $state->questState((string) ($value['quest'] ?? '')) === ($value['state'] ?? null),
            'declinedTimes' => $state->declinedTimes((string) ($value['quest'] ?? '')) >= (int) ($value['atLeast'] ?? 1),
            'gaveTo' => $state->gaveTo((int) ($value['resident'] ?? 0), (int) ($value['item'] ?? 0)) >= (int) ($value['atLeast'] ?? 1),
            'spentWith' => $state->spentWith((int) ($value['resident'] ?? 0)) >= (int) ($value['atLeast'] ?? 1),
            'objectState' => $use->objectState()->has((string) $value),
            'narrator' => true,
            default => false,
        };
    }

    /**
     * The narrator leaves of the condition: the condition itself or the
     * conditions that must all be met, the only places one may sit.
     *
     * @return array<int, array{requirement?: string, outcome?: string}>
     */
    public function narratorLeaves(?array $condition): array
    {
        if ($condition === null || $condition === []) {
            return [];
        }
        $leaves = array_key_first($condition) === 'all' ? $condition['all'] : [$condition];

        return collect($leaves)->filter(fn ($leaf) => is_array($leaf) && array_key_first($leaf) === 'narrator')->map(fn (array $leaf) => $leaf['narrator'])->values()->all();
    }

    /**
     * Why the condition is not met, for the narrator, when it is an item or credits the player lacks.
     */
    public function unmetReason(?array $condition, ActivityUse $use, QuestSessionState $state): ?string
    {
        if ($condition === null || $condition === []) {
            return null;
        }

        $kind = array_key_first($condition);
        $value = $condition[$kind];

        if ($kind === 'all') {
            foreach ($value as $child) {
                if (is_array($child) && ! $this->holds($child, $use, $state)) {
                    return $this->unmetReason($child, $use, $state);
                }
            }

            return null;
        }
        if ($this->holds($condition, $use, $state)) {
            return null;
        }

        return match ($kind) {
            'has' => 'The player does not have '.(($value['atLeast'] ?? 1) > 1 ? $value['atLeast'].' ' : 'the ').(Item::find($value['item'] ?? 0)?->name ?? 'item').' it needs.',
            'credits' => 'The player has fewer than the '.($value['atLeast'] ?? 0).' credits it needs.',
            default => null,
        };
    }

    /**
     * The ids of the items the condition names.
     *
     * @return array<int, int>
     */
    public function itemIds(?array $condition): array
    {
        if ($condition === null || $condition === []) {
            return [];
        }

        $kind = array_key_first($condition);
        $value = $condition[$kind];

        return match ($kind) {
            'all', 'any' => array_values(array_unique(array_merge([], ...array_map(fn ($child) => $this->itemIds(is_array($child) ? $child : null), $value)))),
            'not' => $this->itemIds(is_array($value) ? $value : null),
            'has', 'gaveTo' => [(int) ($value['item'] ?? 0)],
            default => [],
        };
    }

    /**
     * The condition once the item is deleted from the world: the leaves
     * naming it are dropped, and a group left empty goes with them.
     */
    public function withoutItem(?array $condition, int $itemId): ?array
    {
        if ($condition === null || $condition === []) {
            return null;
        }

        $kind = array_key_first($condition);
        $value = $condition[$kind];

        if (in_array($kind, ['all', 'any'], true)) {
            $kept = collect($value)->map(fn ($child) => $this->withoutItem(is_array($child) ? $child : null, $itemId))->filter()->values()->all();

            return match (count($kept)) {
                0 => null,
                1 => $kept[0],
                default => [$kind => $kept],
            };
        }
        if ($kind === 'not') {
            $kept = $this->withoutItem(is_array($value) ? $value : null, $itemId);

            return $kept === null ? null : ['not' => $kept];
        }

        return in_array($kind, ['has', 'gaveTo'], true) && (int) ($value['item'] ?? 0) === $itemId ? null : $condition;
    }

    /**
     * @param  array{atLeast?: float|int, atMost?: float|int}  $bounds
     */
    private function withinBounds(float $sentiment, array $bounds): bool
    {
        return (! isset($bounds['atLeast']) || $sentiment >= $bounds['atLeast'])
            && (! isset($bounds['atMost']) || $sentiment <= $bounds['atMost']);
    }
}
