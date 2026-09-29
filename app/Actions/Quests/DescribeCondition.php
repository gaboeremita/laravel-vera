<?php

namespace App\Actions\Quests;

use App\Models\Fact;
use App\Models\Region;
use App\Models\World;
use App\Models\WorldResident;
use Closure;
use Illuminate\Support\Collection;

/**
 * Puts quest conditions into plain words with the world's names, from the
 * giver's point of view when there is one ("your trust toward the user").
 * Each leaf also splits into what it is about and what it asks, which is how
 * a giver's lookup reports it.
 */
class DescribeCondition
{
    private ?int $worldId = null;

    /** @var Collection<int, Region> */
    private Collection $regions;

    /** @var array<int, string> */
    private array $residentNames = [];

    /** @var array<int, string> */
    private array $itemNames = [];

    /** @var array<int, string> */
    private array $factTopics = [];

    /** @var array<string, string> */
    private array $questTitles = [];

    public function tree(?array $condition, World $world, ?WorldResident $giver = null): string
    {
        if ($condition === null || $condition === []) {
            return 'nothing';
        }

        $kind = array_key_first($condition);
        $value = $condition[$kind];

        return match ($kind) {
            'all' => $this->join($value, ' and ', $world, $giver),
            'any' => 'either '.$this->join($value, ' or ', $world, $giver),
            'not' => 'not ('.$this->tree(is_array($value) ? $value : null, $world, $giver).')',
            default => $this->leaf($condition, $world, $giver)['sentence'],
        };
    }

    /**
     * @param  array<string, mixed>  $leaf
     * @return array{sentence: string, subject: string, asks: string}
     */
    public function leaf(array $leaf, World $world, ?WorldResident $giver = null): array
    {
        $this->load($world);
        $kind = array_key_first($leaf);
        $value = $leaf[$kind];
        $who = fn (mixed $residentId): string => $giver !== null && (int) $residentId === $giver->id ? 'you' : $this->resident($residentId);

        [$subject, $asks, $sentence] = match ($kind) {
            'enterRegion' => $this->event("the user has entered {$this->region($value)}"),
            'enterZone' => $this->event("the user has entered {$this->zone($value)}"),
            'talkTo' => $this->event("the user has talked to {$who($value)}"),
            'use' => $this->event("the user has done {$this->activity($value)}"),
            'residentDid' => $this->event(ucfirst($who($value['resident'] ?? null))." {$this->did($who($value['resident'] ?? null))} {$this->activity($value)}"),
            'has' => ["how many {$this->item($value['item'] ?? null)} the user holds", 'at least '.($value['atLeast'] ?? 1), 'the user holds at least '.($value['atLeast'] ?? 1)." {$this->item($value['item'] ?? null)}"],
            'credits' => ['how many credits the user holds', 'at least '.($value['atLeast'] ?? 0), 'the user holds at least '.($value['atLeast'] ?? 0).' credits'],
            'knows' => $this->fact("the user knows \"{$this->factTopic($value)}\""),
            'acknowledged' => $this->fact(ucfirst($who($value['resident'] ?? null))." {$this->has($who($value['resident'] ?? null))} learned \"{$this->factTopic($value['fact'] ?? null)}\" from the user"),
            'flag' => $this->fact(is_string($value) ? "the flag {$value} is set" : "\"{$this->quest($value['quest'] ?? null)}\" ended with the flag ".($value['name'] ?? '')),
            'question' => $this->fact("the question {$value} is met"),
            'beat' => $this->fact("the beat {$value} is finished"),
            'feeling' => $this->feeling($value, $who($value['resident'] ?? null)),
            'questState' => ["where \"{$this->quest($value['quest'] ?? null)}\" stands", (string) ($value['state'] ?? ''), "\"{$this->quest($value['quest'] ?? null)}\" is ".($value['state'] ?? '')],
            'declinedTimes' => $this->atLeast("how many times the user has turned down \"{$this->quest($value['quest'] ?? null)}\"", $value, fn (int $count) => "the user has turned down \"{$this->quest($value['quest'] ?? null)}\" at least {$count} times"),
            'gaveTo' => $this->atLeast("how many {$this->item($value['item'] ?? null)} the user has given {$who($value['resident'] ?? null)}", $value, fn (int $count) => "the user has given {$who($value['resident'] ?? null)} at least {$count} {$this->item($value['item'] ?? null)}"),
            'spentWith' => $this->atLeast("how many credits the user has paid {$who($value['resident'] ?? null)}", $value, fn (int $count) => "the user has paid {$who($value['resident'] ?? null)} at least {$count} credits"),
            'messagesWith' => $this->atLeast("how many messages the user has sent {$who($value['resident'] ?? null)}", $value, fn (int $count) => "the user has sent {$who($value['resident'] ?? null)} at least {$count} messages"),
            'giverIn' => [$giver !== null ? 'the zone you are in' : "the giver's zone", $this->zone($value), ($giver !== null ? 'you are in ' : 'the giver is in ').$this->zone($value)],
            'othersInTheZone' => $this->others($value, $giver !== null),
            default => [$kind, '', $kind],
        };

        return ['sentence' => $sentence, 'subject' => $subject, 'asks' => $asks];
    }

    public function zone(mixed $value): string
    {
        $this->loadRegions();
        $region = $this->regions->get($value['region'] ?? null);
        $zone = collect($region->layout['zones'] ?? [])->firstWhere('id', $value['zone'] ?? null);

        return ($zone['name'] ?? $value['zone'] ?? 'an unknown zone').($region !== null ? " in {$region->name}" : '');
    }

    public function resident(mixed $residentId): string
    {
        return $this->residentNames[(int) $residentId] ?? 'someone';
    }

    public function item(mixed $itemId): string
    {
        return $this->itemNames[(int) $itemId] ?? 'an unknown item';
    }

    public function quest(mixed $questKey): string
    {
        return $this->questTitles[(string) $questKey] ?? (string) $questKey;
    }

    /**
     * @param  array<int, mixed>  $children
     */
    private function join(mixed $children, string $glue, World $world, ?WorldResident $giver): string
    {
        $parts = collect(is_array($children) ? $children : [])
            ->map(function (mixed $child) use ($world, $giver): string {
                $text = $this->tree(is_array($child) ? $child : null, $world, $giver);

                return is_array($child) && in_array(array_key_first($child), ['all', 'any'], true) ? "({$text})" : $text;
            });

        return $parts->implode($glue);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function event(string $sentence): array
    {
        return ["whether {$sentence}", 'has happened', $sentence];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function fact(string $sentence): array
    {
        return ["whether {$sentence}", 'yes', $sentence];
    }

    /**
     * @param  Closure(int): string  $sentence
     * @return array{0: string, 1: string, 2: string}
     */
    private function atLeast(string $subject, mixed $value, Closure $sentence): array
    {
        $count = (int) ($value['atLeast'] ?? 1);

        return [$subject, "at least {$count}", $sentence($count)];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function feeling(mixed $value, string $who): array
    {
        $subject = ($who === 'you' ? 'your' : "{$who}'s").' '.($value['kind'] ?? 'feeling').' toward the user';
        $asks = collect(['atLeast' => 'at least', 'atMost' => 'at most'])
            ->filter(fn (string $words, string $bound) => isset($value[$bound]))
            ->map(fn (string $words, string $bound) => "{$words} {$value[$bound]}")
            ->implode(' and ');

        return [$subject, $asks, "{$subject} is {$asks}"];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function others(mixed $value, bool $asGiver): array
    {
        $subject = $asGiver ? 'who else is in your zone' : "who else is in the giver's zone";
        if (($value['nobody'] ?? false) === true) {
            return [$subject, 'nobody', $asGiver ? 'nobody else is in your zone' : "nobody else is in the giver's zone"];
        }

        $name = $this->resident($value['resident'] ?? null);

        return [$subject, $name, $asGiver ? "{$name} is in your zone" : "{$name} is in the giver's zone"];
    }

    private function region(mixed $regionId): string
    {
        $this->loadRegions();

        return $this->regions->get((int) $regionId)?->name ?? 'an unknown region';
    }

    private function activity(mixed $value): string
    {
        $this->loadRegions();
        $region = $this->regions->get($value['region'] ?? null);
        $object = $region?->layoutObject((string) ($value['object'] ?? ''));
        $activity = $region?->objectActivities((string) ($value['object'] ?? ''))[$value['activity'] ?? ''] ?? null;

        return ($activity['name'] ?? $value['activity'] ?? 'an activity').' at '.($object['name'] ?? $value['object'] ?? 'an object');
    }

    private function factTopic(mixed $factId): string
    {
        return $this->factTopics[(int) $factId] ?? 'an unknown fact';
    }

    private function did(string $who): string
    {
        return $who === 'you' ? 'have done' : 'has done';
    }

    private function has(string $who): string
    {
        return $who === 'you' ? 'have' : 'has';
    }

    /**
     * Loads the world's names once; the name helpers read them.
     */
    public function forWorld(World $world): self
    {
        $this->load($world);

        return $this;
    }

    private function load(World $world): void
    {
        if ($this->worldId === $world->id) {
            return;
        }

        $this->worldId = $world->id;
        $this->regions = $world->regions()->get(['id', 'name', 'layout'])->keyBy('id');
        $this->residentNames = $world->residents()->with('assistant')->get()->mapWithKeys(fn (WorldResident $resident) => [$resident->id => $resident->assistant->name])->all();
        $this->itemNames = $world->items()->pluck('name', 'id')->all();
        $this->factTopics = Fact::whereHas('holder', fn ($query) => $query->where('world_id', $world->id))->pluck('topic', 'id')->all();
        $this->questTitles = $world->quests()->pluck('title', 'key')->all();
    }

    private function loadRegions(): void
    {
        $this->regions ??= collect();
    }
}
