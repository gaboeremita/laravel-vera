<?php

namespace App\Actions;

use App\Models\Region;
use App\Models\WorldResident;
use App\Models\WorldSession;

class ResolveResidentRegion
{
    /**
     * The region the resident is in during the session: where the session last
     * put her, or her own region while the session holds no state for her.
     */
    public function handle(WorldSession $session, WorldResident $resident): Region
    {
        $state = $session->residentStates()->where('world_resident_id', $resident->id)->first();

        return $state?->region ?? $resident->region;
    }

    /**
     * The resident's region, which must be the region the player is in.
     */
    public function inCurrentRegion(WorldSession $session, WorldResident $resident): Region
    {
        $region = $this->handle($session, $resident);

        abort_if($session->region_id !== null && $region->id !== $session->region_id, 422, 'This resident is not in the region you are in.');

        return $region;
    }
}
