<?php

namespace App\Actions;

use App\Events\Quests\ResidentUsedActivity;
use App\Models\Region;
use App\Models\ResidentActivity;
use App\Models\WorldResident;
use App\Models\WorldSession;

/**
 * Records what a resident chose to do, and tells quests when it is one of an
 * object's activities.
 */
class RecordResidentActivity
{
    /**
     * @param  array{source: string, verb: string, target: ?string, activity: ?string, reason: ?string, narration: ?string, zone_id: ?string}  $attributes
     */
    public function handle(WorldSession $session, WorldResident $resident, Region $region, array $attributes): ResidentActivity
    {
        $activity = ResidentActivity::create([
            'world_session_id' => $session->id,
            'world_resident_id' => $resident->id,
            ...$attributes,
        ]);

        $objectId = $attributes['verb'] === 'use' && $attributes['activity'] !== null && $attributes['target'] !== null
            ? $this->objectOfSpot($region, $attributes['target'])
            : null;
        if ($objectId !== null) {
            ResidentUsedActivity::dispatch($session->id, $resident->id, $region->id, $objectId, $attributes['activity']);
        }

        return $activity;
    }

    private function objectOfSpot(Region $region, string $spotId): ?string
    {
        return collect($region->layout['objects'] ?? [])
            ->first(fn (array $object) => collect($object['spots'] ?? [])->contains('id', $spotId))['id'] ?? null;
    }
}
