<?php

namespace App\Actions;

use App\Models\ActivityTerms;
use App\Models\Inventory;
use App\Models\Region;
use App\Models\StartingInventory;
use App\Models\WorldSessionObject;

class ReconcilePassages
{
    public function __construct(private readonly LinkPassages $linkPassages) {}

    /**
     * Drops the links and the world's spawn point that refer to passages the
     * region's layout no longer has, and what was configured on objects it no longer has.
     *
     * @return array<int, string> the ids of passages whose link was removed
     */
    public function handle(Region $region): array
    {
        $passageIds = collect($region->layout['passages'] ?? [])->pluck('id')->all();
        $removed = $region->passageLinks()->whereNotIn('passage_id', $passageIds)->pluck('passage_id')->all();

        foreach ($removed as $passageId) {
            $this->linkPassages->unlink($region, $passageId);
        }

        $world = $region->world;
        if ($world->spawn_region_id === $region->id && ! in_array($world->spawn_passage_id, $passageIds, true)) {
            $world->update(['spawn_region_id' => null, 'spawn_passage_id' => null]);
        }

        $this->dropVanishedObjects($region);

        return $removed;
    }

    /**
     * Drops the starting inventories, session inventories, session states and
     * activity terms of objects, or activities of an object, the layout no
     * longer has.
     */
    private function dropVanishedObjects(Region $region): void
    {
        $objectIds = collect($region->layout['objects'] ?? [])->pluck('id')->all();

        StartingInventory::where('region_id', $region->id)->whereNotIn('object_id', $objectIds)->delete();
        Inventory::where('region_id', $region->id)->whereNotIn('object_id', $objectIds)->delete();
        WorldSessionObject::where('region_id', $region->id)->whereNotIn('object_id', $objectIds)->delete();
        $region->activityTerms()->get()
            ->reject(fn (ActivityTerms $terms) => array_key_exists($terms->activity_id, $region->objectActivities($terms->object_id)))
            ->each->delete();
    }
}
