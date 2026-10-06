<?php

namespace App\Actions\Quests;

use App\Models\Quest;
use App\Models\WorldResident;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Reads a quest's offerWhen for its giver: its parts in plain words, the
 * value each reads right now next to what the quest asks, and whether the
 * whole holds. The giver weighs these themselves; the game only records them.
 */
class LookUpOfferCondition
{
    public const SCOPE = 'offer';

    public function __construct(
        private readonly QuestConditions $conditions,
        private readonly DescribeCondition $describeCondition,
    ) {}

    /**
     * Each leaf of the quest's offerWhen, with the path the evaluator knows it
     * by and a label unique within the quest.
     *
     * @return Collection<int, array{path: string, leaf: array<string, mixed>, label: string, subject: string, asks: string}>
     */
    public function parts(Quest $quest, WorldResident $giver): Collection
    {
        $parts = collect($this->leaves($quest->offerWhen(), ''))
            ->map(fn (array $entry) => [...$entry, ...$this->describeCondition->leaf($entry['leaf'], $quest->world, $giver)])
            ->values();

        return $parts->map(function (array $part, int $index) use ($parts): array {
            $same = $parts->take($index)->where('sentence', $part['sentence'])->count();

            return [
                'path' => $part['path'],
                'leaf' => $part['leaf'],
                'label' => $same === 0 ? $part['sentence'] : "{$part['sentence']} ({$same})",
                'subject' => $part['subject'],
                'asks' => $part['asks'],
            ];
        });
    }

    /**
     * @return array{part: string, value: string, asks: string}
     *
     * @throws InvalidArgumentException when the label isn't a part of the quest's offerWhen
     */
    public function lookUp(WorldSessionQuest $run, string $label, QuestSessionState $state, OfferMoment $moment): array
    {
        $part = $this->parts($run->quest, $moment->giver)->firstWhere('label', $label)
            ?? throw new InvalidArgumentException('That isn\'t part of what this task asks. Its parts are: '.$this->parts($run->quest, $moment->giver)->pluck('label')->implode('; ').'.');

        return ['part' => $part['subject'], 'value' => $this->value($part, $run, $state, $moment), 'asks' => $part['asks']];
    }

    public function holds(WorldSessionQuest $run, QuestSessionState $state, OfferMoment $moment): bool
    {
        return $this->conditions->holds($run->quest->offerWhen(), $run, $state, self::SCOPE, '', $moment);
    }

    /**
     * The labels of the parts that don't hold right now.
     *
     * @return array<int, string>
     */
    public function unmetParts(WorldSessionQuest $run, QuestSessionState $state, OfferMoment $moment): array
    {
        return $this->parts($run->quest, $moment->giver)
            ->reject(fn (array $part) => $this->conditions->holds($part['leaf'], $run, $state, self::SCOPE, $part['path'], $moment))
            ->pluck('label')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{path: string, leaf: array<string, mixed>}>
     */
    private function leaves(?array $condition, string $path): array
    {
        if ($condition === null || $condition === []) {
            return [];
        }

        $kind = array_key_first($condition);
        $value = $condition[$kind];

        return match ($kind) {
            'all', 'any' => array_merge([], ...array_map(fn ($child, $index) => $this->leaves(is_array($child) ? $child : null, "{$path}.{$kind}.{$index}"), $value, array_keys($value))),
            'not' => $this->leaves(is_array($value) ? $value : null, "{$path}.not"),
            default => [['path' => $path, 'leaf' => $condition]],
        };
    }

    /**
     * @param  array{path: string, leaf: array<string, mixed>}  $part
     */
    private function value(array $part, WorldSessionQuest $run, QuestSessionState $state, OfferMoment $moment): string
    {
        $kind = array_key_first($part['leaf']);
        $value = $part['leaf'][$kind];
        $holds = fn (): bool => $this->conditions->holds($part['leaf'], $run, $state, self::SCOPE, $part['path'], $moment);
        $names = $this->describeCondition->forWorld($run->quest->world);

        return match ($kind) {
            'enterRegion', 'enterZone', 'talkTo', 'use', 'residentDid' => $holds() ? 'has happened' : 'hasn\'t happened',
            'has' => (string) ($state->quantityOf((int) ($value['item'] ?? 0)) ?? 'unlimited'),
            'credits' => (string) ($state->credits() ?? 'unlimited'),
            'sentiment' => number_format($state->sentiment((int) ($value['resident'] ?? 0), (string) ($value['kind'] ?? '')), 1),
            'questState' => $state->questState((string) ($value['quest'] ?? '')) ?? 'not available',
            'declinedTimes' => (string) $state->declinedTimes((string) ($value['quest'] ?? '')),
            'gaveTo' => (string) $state->gaveTo((int) ($value['resident'] ?? 0), (int) ($value['item'] ?? 0)),
            'spentWith' => (string) $state->spentWith((int) ($value['resident'] ?? 0)),
            'messagesWith' => (string) $this->messagesWith($value, $moment),
            'giverIn' => $this->giverZone($value, $moment, $names),
            'othersInTheZone' => collect($moment->residentsInGiverZone())->map(fn (int $residentId) => $names->resident($residentId))->implode(', ') ?: 'nobody',
            default => $holds() ? 'yes' : 'no',
        };
    }

    private function messagesWith(mixed $value, OfferMoment $moment): int
    {
        $resident = WorldResident::find((int) ($value['resident'] ?? 0));

        return $resident === null ? 0 : $moment->messagesWith($resident);
    }

    private function giverZone(mixed $value, OfferMoment $moment, DescribeCondition $names): string
    {
        $named = collect($moment->region?->id === ($value['region'] ?? null) ? $moment->region->layout['zones'] ?? [] : [])->firstWhere('id', $value['zone'] ?? null);
        if ($named === null && $moment->region?->id === ($value['region'] ?? null)) {
            return "{$value['zone']} no longer exists in {$moment->region->name}";
        }

        $zone = collect($moment->giverZoneChain())->last();

        return $zone === null ? 'no zone' : ($zone['name'] ?? $zone['id']).($moment->region !== null ? " in {$moment->region->name}" : '');
    }
}
