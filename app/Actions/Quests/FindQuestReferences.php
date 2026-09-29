<?php

namespace App\Actions\Quests;

use App\Exceptions\UsedByQuests;
use App\Models\Fact;
use App\Models\Item;
use App\Models\Quest;
use App\Models\Region;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Support\Collection;

/**
 * Finds the quests whose definitions name a region, resident, item or fact,
 * so none of them can be deleted from under a quest.
 */
class FindQuestReferences
{
    /**
     * @throws UsedByQuests
     */
    public function ensureRegionUnused(Region $region): void
    {
        $this->ensureUnused($region->world, 'regions', $region->id, $region->name);
    }

    /**
     * @throws UsedByQuests
     */
    public function ensureResidentUnused(WorldResident $resident): void
    {
        $this->ensureUnused($resident->world, 'residents', $resident->id, $resident->assistant->name);
    }

    /**
     * @throws UsedByQuests
     */
    public function ensureItemUnused(Item $item): void
    {
        $this->ensureUnused($item->world, 'items', $item->id, $item->name);
    }

    /**
     * @throws UsedByQuests
     */
    public function ensureFactUnused(Fact $fact): void
    {
        $this->ensureUnused($fact->holder->world, 'facts', $fact->id, "The fact \"{$fact->topic}\"");
    }

    /**
     * @return Collection<int, Quest>
     */
    public function using(World $world, string $kind, int $id): Collection
    {
        return $world->quests()->orderBy('title')->get()
            ->filter(fn (Quest $quest) => in_array($id, $this->references($quest->definition)[$kind], true))
            ->values();
    }

    /**
     * @throws UsedByQuests
     */
    private function ensureUnused(World $world, string $kind, int $id, string $name): void
    {
        $quests = $this->using($world, $kind, $id);
        if ($quests->isNotEmpty()) {
            throw new UsedByQuests($name, $quests);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{regions: array<int, int>, residents: array<int, int>, items: array<int, int>, facts: array<int, int>}
     */
    private function references(array $definition): array
    {
        $found = ['regions' => [], 'residents' => [], 'items' => [], 'facts' => []];
        $add = function (string $kind, mixed $id) use (&$found): void {
            if (is_int($id)) {
                $found[$kind][] = $id;
            }
        };

        $walk = function (mixed $node) use (&$walk, $add): void {
            if (! is_array($node) || $node === []) {
                return;
            }
            $kind = array_key_first($node);
            $value = $node[$kind];
            match ($kind) {
                'all', 'any' => array_map($walk, is_array($value) ? $value : []),
                'not' => $walk($value),
                'enterRegion' => $add('regions', $value),
                'enterZone', 'use' => $add('regions', $value['region'] ?? null),
                'talkTo' => $add('residents', $value),
                'residentDid' => [$add('residents', $value['resident'] ?? null), $add('regions', $value['region'] ?? null)],
                'has' => $add('items', $value['item'] ?? null),
                'knows' => $add('facts', $value),
                'acknowledged' => [$add('facts', $value['fact'] ?? null), $add('residents', $value['resident'] ?? null)],
                default => null,
            };
        };

        $add('residents', $definition['start']['giver'] ?? null);
        $add('residents', $definition['reward']['from']['resident'] ?? null);
        $add('regions', $definition['reward']['from']['object']['region'] ?? null);
        $walk($definition['start']['when'] ?? null);
        $walk($definition['complete'] ?? null);
        $walk($definition['fail'] ?? null);
        foreach ($definition['beats'] ?? [] as $beat) {
            $walk($beat['when'] ?? null);
            foreach ($beat['knowledge'] ?? [] as $knowledge) {
                $add('residents', $knowledge['resident'] ?? null);
            }
            foreach ($beat['grants'] ?? [] as $grant) {
                $add('residents', $grant['resident'] ?? null);
            }
            foreach ($beat['questions'] ?? [] as $question) {
                foreach ($question['residents'] ?? [] as $residentId) {
                    $add('residents', $residentId);
                }
            }
        }

        return $found;
    }
}
