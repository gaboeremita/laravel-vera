<?php

namespace App\Actions;

use App\Models\Region;

class ReconcilePassages
{
    public function __construct(private readonly LinkPassages $linkPassages) {}

    /**
     * Drops the links and the world's spawn point that refer to passages the
     * region's layout no longer has.
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

        return $removed;
    }
}
